<?php

namespace App\Features\Global\Services;

use App\Features\Global\Models\GeoComplianceRule;
use App\Features\Global\Support\ComplianceAction;
use App\Features\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class GeoComplianceService
{
    /**
     * @return array{
     *   action: string,
     *   allowed: bool,
     *   min_age: int|null,
     *   age_ok: bool,
     *   blocked_features: list<string>,
     *   matched_rule_id: string|null,
     *   reason: string|null,
     *   country_code: string,
     *   region_code: string|null,
     *   feature: string
     * }
     */
    public function evaluate(
        ?User $user,
        string $countryCode,
        ?string $regionCode = null,
        string $feature = '*',
        ?int $age = null,
    ): array {
        $country = strtoupper(trim($countryCode));
        if ($country === '') {
            $country = '*';
        }
        $region = $regionCode !== null && $regionCode !== '' ? strtoupper(trim($regionCode)) : null;
        $featureKey = $feature !== '' ? $feature : '*';

        $resolvedAge = $age;
        if ($resolvedAge === null && $user !== null) {
            $resolvedAge = $this->ageFromUser($user);
        }

        $rules = GeoComplianceRule::query()
            ->where('active', true)
            ->where(function ($q) use ($country): void {
                $q->where('country_code', $country)->orWhere('country_code', '*');
            })
            ->where(function ($q) use ($featureKey): void {
                $q->where('feature_key', $featureKey)->orWhere('feature_key', '*');
            })
            ->orderBy('priority')
            ->get();

        $matched = null;
        foreach ($rules as $rule) {
            if ($rule->region_code !== null && $region !== null && strtoupper((string) $rule->region_code) !== $region) {
                continue;
            }
            if ($rule->region_code !== null && $region === null && $rule->country_code !== '*') {
                // Region-specific rule without a region context — skip unless country-wide.
                continue;
            }
            $matched = $rule;
            break;
        }

        if ($matched === null) {
            $minAge = (int) config('global.compliance.default_min_age', 13);
            $action = (string) config('global.compliance.default_action', ComplianceAction::ALLOW);
            $ageOk = $resolvedAge === null || $resolvedAge >= $minAge;

            return $this->result(
                action: $ageOk ? $action : ComplianceAction::RESTRICT,
                minAge: $minAge,
                ageOk: $ageOk,
                blocked: (! $ageOk || $action === ComplianceAction::DENY) ? [$featureKey] : [],
                ruleId: null,
                reason: 'default_fallback',
                country: $country,
                region: $region,
                feature: $featureKey,
            );
        }

        $minAge = $matched->min_age !== null ? (int) $matched->min_age : null;
        $ageOk = $minAge === null || $resolvedAge === null || $resolvedAge >= $minAge;
        $action = (string) $matched->action;

        if (! $ageOk && $action === ComplianceAction::ALLOW) {
            $action = ComplianceAction::RESTRICT;
        }

        $blocked = [];
        if ($action === ComplianceAction::DENY || (! $ageOk && $action === ComplianceAction::RESTRICT)) {
            $blocked[] = $featureKey === '*' ? 'all' : $featureKey;
        }

        return $this->result(
            action: $action,
            minAge: $minAge,
            ageOk: $ageOk,
            blocked: $blocked,
            ruleId: $matched->getKey(),
            reason: $matched->reason,
            country: $country,
            region: $region,
            feature: $featureKey,
        );
    }

    /**
     * @param  list<string>  $blocked
     * @return array<string, mixed>
     */
    private function result(
        string $action,
        ?int $minAge,
        bool $ageOk,
        array $blocked,
        ?string $ruleId,
        ?string $reason,
        string $country,
        ?string $region,
        string $feature,
    ): array {
        return [
            'action' => $action,
            'allowed' => $action === ComplianceAction::ALLOW && $ageOk,
            'min_age' => $minAge,
            'age_ok' => $ageOk,
            'blocked_features' => $blocked,
            'matched_rule_id' => $ruleId,
            'reason' => $reason,
            'country_code' => $country,
            'region_code' => $region,
            'feature' => $feature,
        ];
    }

    private function ageFromUser(User $user): ?int
    {
        $dob = $user->profile?->date_of_birth ?? $user->date_of_birth ?? null;
        if ($dob === null) {
            return null;
        }

        try {
            return Carbon::parse($dob)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    public function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use global compliance tools.');
        }
    }
}
