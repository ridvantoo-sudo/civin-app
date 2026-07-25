<?php

namespace App\Filament\Resources\SecurityRuleResource\Pages;

use App\Features\Security\Models\SecurityRule;
use App\Features\Security\Services\SecurityAdminService;
use App\Filament\Resources\SecurityRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditSecurityRule extends EditRecord
{
    protected static string $resource = SecurityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->after(fn () => app(SecurityAdminService::class)
                ->ruleChanged($this->record, (string) Auth::id(), 'deleted')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var SecurityRule $rule */
        $rule = $this->record;

        app(SecurityAdminService::class)->ruleChanged($rule, (string) Auth::id(), 'updated');
    }
}
