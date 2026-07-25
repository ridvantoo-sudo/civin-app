<?php

namespace App\Filament\Resources\SecurityBanResource\Pages;

use App\Filament\Resources\SecurityBanResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSecurityBan extends CreateRecord
{
    protected static string $resource = SecurityBanResource::class;

    /**
     * Routed through the admin service so the restriction is audited exactly
     * like an engine-issued one.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return SecurityBanResource::issueFromForm($data);
    }
}
