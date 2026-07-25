<?php

namespace App\Filament\Resources\MarketItemResource\Pages;

use App\Filament\Resources\MarketItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketItem extends EditRecord
{
    protected static string $resource = MarketItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
