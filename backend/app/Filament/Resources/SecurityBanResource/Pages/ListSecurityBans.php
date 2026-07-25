<?php

namespace App\Filament\Resources\SecurityBanResource\Pages;

use App\Filament\Resources\SecurityBanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSecurityBans extends ListRecords
{
    protected static string $resource = SecurityBanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Issue restriction'),
        ];
    }
}
