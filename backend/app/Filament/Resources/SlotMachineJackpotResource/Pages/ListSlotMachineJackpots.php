<?php

namespace App\Filament\Resources\SlotMachineJackpotResource\Pages;

use App\Filament\Resources\SlotMachineJackpotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlotMachineJackpots extends ListRecords
{
    protected static string $resource = SlotMachineJackpotResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
