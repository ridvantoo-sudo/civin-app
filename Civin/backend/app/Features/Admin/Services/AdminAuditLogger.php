<?php

namespace App\Features\Admin\Services;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

final class AdminAuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(User $admin, string $description, ?Model $subject = null, array $properties = []): Activity
    {
        $logger = activity('admin')
            ->causedBy($admin)
            ->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        return $logger->log($description);
    }
}
