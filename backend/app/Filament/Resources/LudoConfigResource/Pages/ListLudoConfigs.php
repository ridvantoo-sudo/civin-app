<?php

namespace App\Filament\Resources\LudoConfigResource\Pages;

use App\Filament\Resources\LudoConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLudoConfigs extends ListRecords
{
    protected static string $resource = LudoConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
