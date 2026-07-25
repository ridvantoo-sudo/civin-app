<?php

namespace App\Filament\Resources\TreasureBoxConfigResource\Pages;

use App\Filament\Resources\TreasureBoxConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTreasureBoxConfigs extends ListRecords
{
    protected static string $resource = TreasureBoxConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
