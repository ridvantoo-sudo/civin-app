<?php

namespace App\Filament\Resources\SecurityRuleResource\Pages;

use App\Features\Security\Models\SecurityRule;
use App\Features\Security\Services\SecurityAdminService;
use App\Filament\Resources\SecurityRuleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSecurityRule extends CreateRecord
{
    protected static string $resource = SecurityRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var SecurityRule $rule */
        $rule = $this->record;

        app(SecurityAdminService::class)->ruleChanged($rule, (string) Auth::id(), 'created');
    }
}
