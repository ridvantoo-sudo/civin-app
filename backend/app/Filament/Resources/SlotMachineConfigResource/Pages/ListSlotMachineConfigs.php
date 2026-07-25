<?php

namespace App\Filament\Resources\SlotMachineConfigResource\Pages;

use App\Filament\Resources\SlotMachineConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlotMachineConfigs extends ListRecords
{
    protected static string $resource = SlotMachineConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
