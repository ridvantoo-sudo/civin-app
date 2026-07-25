<?php

namespace App\Filament\Resources\SlotMachinePaylineResource\Pages;

use App\Filament\Resources\SlotMachinePaylineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlotMachinePaylines extends ListRecords
{
    protected static string $resource = SlotMachinePaylineResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
