<?php

namespace App\Filament\Resources\SlotMachineSymbolResource\Pages;

use App\Filament\Resources\SlotMachineSymbolResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlotMachineSymbols extends ListRecords
{
    protected static string $resource = SlotMachineSymbolResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
