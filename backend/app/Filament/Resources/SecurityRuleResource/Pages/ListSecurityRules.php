<?php

namespace App\Filament\Resources\SecurityRuleResource\Pages;

use App\Features\Security\Rules\RuleProvider;
use App\Filament\Resources\SecurityRuleResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSecurityRules extends ListRecords
{
    protected static string $resource = SecurityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('reload')
                ->label('Hot reload rules')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (RuleProvider $rules): void {
                    $rules->flush();

                    Notification::make()
                        ->title('Rules reloaded')
                        ->body(count($rules->all()).' rules are now active.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
