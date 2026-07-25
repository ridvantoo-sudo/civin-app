<?php

namespace App\Filament\Resources\LuckyWheelSectorResource\Pages;

use App\Filament\Resources\LuckyWheelSectorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLuckyWheelSectors extends ListRecords
{
    protected static string $resource = LuckyWheelSectorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
