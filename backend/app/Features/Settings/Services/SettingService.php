<?php

namespace App\Features\Settings\Services;

use App\Features\GeoDiscovery\Services\GeoDiscoveryService;
use App\Features\Settings\Repositories\Contracts\SettingRepository;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class SettingService
{
    public function __construct(private SettingRepository $settings) {}

    public function publicValues(): Collection
    {
        return $this->settings->publicValues();
    }

    public function userValues(User $user): Collection
    {
        return $this->settings->userValues($user);
    }

    public function updateUserValues(User $user, array $values): Collection
    {
        $updated = $this->settings->upsertUserValues($user, $values);

        $privacy = array_intersect_key($values, array_flip([
            'location_sharing_enabled',
            'show_distance',
            'nearby_visibility',
        ]));
        if ($privacy !== []) {
            $mapped = [];
            if (array_key_exists('location_sharing_enabled', $privacy)) {
                $mapped['share_location'] = (bool) $privacy['location_sharing_enabled'];
            }
            if (array_key_exists('show_distance', $privacy)) {
                $mapped['show_distance'] = (bool) $privacy['show_distance'];
            }
            if (array_key_exists('nearby_visibility', $privacy)) {
                $mapped['nearby_visibility'] = (string) $privacy['nearby_visibility'];
            }
            app(GeoDiscoveryService::class)
                ->updatePrivacy($user, $mapped);
        }

        return $updated;
    }
}
