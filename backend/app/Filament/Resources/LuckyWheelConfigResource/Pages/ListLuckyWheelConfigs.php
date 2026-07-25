<?php

namespace App\Filament\Resources\LuckyWheelConfigResource\Pages;

use App\Filament\Resources\LuckyWheelConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLuckyWheelConfigs extends ListRecords
{
    protected static string $resource = LuckyWheelConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
