<?php

namespace App\Filament\Resources\MarketItemResource\Pages;

use App\Filament\Resources\MarketItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketItems extends ListRecords
{
    protected static string $resource = MarketItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
