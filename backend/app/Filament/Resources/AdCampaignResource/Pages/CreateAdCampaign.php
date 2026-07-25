<?php

namespace App\Filament\Resources\AdCampaignResource\Pages;

use App\Filament\Resources\AdCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdCampaign extends CreateRecord
{
    protected static string $resource = AdCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_admin_campaign'] = $data['is_admin_campaign'] ?? true;
        $data['reviewed_at'] = now();
        $data['reviewed_by'] = auth()->id();

        return $data;
    }
}
