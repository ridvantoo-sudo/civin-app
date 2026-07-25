<?php

namespace App\Features\Global\Services;

use App\Features\Global\Models\TimezoneSchedule;
use App\Features\Global\Models\UserTimezonePreference;
use App\Features\Global\Support\TimezoneScheduleStatus;
use App\Features\Users\Models\User;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final readonly class TimezoneService
{
    /** @return list<string> */
    public function catalog(): array
    {
        /** @var list<string> $list */
        $list = config('global.timezone.catalog', ['UTC']);

        return array_values(array_unique($list));
    }

    public function getPreference(User $user): array
    {
        $this->ensureEligible($user);
        $pref = UserTimezonePreference::query()->where('user_id', $user->getKey())->first();
        $tz = $pref?->timezone ?: (string) config('global.timezone.default', 'Asia/Baghdad');

        return [
            'timezone' => $tz,
            'is_default' => $pref === null,
            'offset' => $this->offsetLabel($tz),
        ];
    }

    public function setPreference(User $user, string $timezone): array
    {
        $this->ensureEligible($user);
        $timezone = $this->assertValidTimezone($timezone);

        UserTimezonePreference::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['timezone' => $timezone],
        );

        return $this->getPreference($user);
    }

    /**
     * @return array{timezone: string, local: string, utc: string, unix: int}
     */
    public function convert(?User $user, string $localWallTime, ?string $timezone = null): array
    {
        if ($user !== null) {
            $this->ensureEligible($user);
        }

        $tz = $timezone ?: ($user !== null
            ? ($this->getPreference($user)['timezone'] ?? null)
            : null);
        $tz = $this->assertValidTimezone($tz ?: (string) config('global.timezone.default', 'UTC'));

        try {
            $local = Carbon::parse($localWallTime, $tz);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['local' => 'Invalid local datetime.']);
        }

        $utc = $local->clone()->utc();

        return [
            'timezone' => $tz,
            'local' => $local->format('Y-m-d H:i:s'),
            'utc' => $utc->toIso8601String(),
            'unix' => $utc->timestamp,
        ];
    }

    public function schedule(
        User $user,
        string $name,
        string $localWallTime,
        ?string $timezone = null,
        ?array $payload = null,
    ): TimezoneSchedule {
        $this->ensureEligible($user);
        $converted = $this->convert($user, $localWallTime, $timezone);

        return TimezoneSchedule::query()->create([
            'user_id' => $user->getKey(),
            'name' => $name,
            'run_at_utc' => Carbon::createFromTimestampUTC($converted['unix']),
            'timezone' => $converted['timezone'],
            'local_wall_time' => $converted['local'],
            'status' => TimezoneScheduleStatus::SCHEDULED,
            'payload' => $payload,
        ]);
    }

    /** @return Collection<int, TimezoneSchedule> */
    public function mySchedules(User $user): Collection
    {
        $this->ensureEligible($user);

        return TimezoneSchedule::query()
            ->where('user_id', $user->getKey())
            ->orderBy('run_at_utc')
            ->limit(100)
            ->get();
    }

    public function processDue(int $limit = 50): int
    {
        $due = TimezoneSchedule::query()
            ->where('status', TimezoneScheduleStatus::SCHEDULED)
            ->where('run_at_utc', '<=', now('UTC'))
            ->orderBy('run_at_utc')
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($due as $row) {
            $row->forceFill([
                'status' => TimezoneScheduleStatus::DONE,
                'processed_at' => now(),
            ])->save();
            $count++;
        }

        return $count;
    }

    private function assertValidTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        $catalog = $this->catalog();
        if (! in_array($timezone, $catalog, true) && ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages(['timezone' => 'Unsupported timezone.']);
        }

        try {
            new DateTimeZone($timezone);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['timezone' => 'Invalid IANA timezone.']);
        }

        return $timezone;
    }

    private function offsetLabel(string $timezone): string
    {
        try {
            $offset = Carbon::now($timezone)->format('P');

            return 'UTC'.$offset;
        } catch (\Throwable) {
            return 'UTC';
        }
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can manage timezone preferences.');
        }
    }
}
