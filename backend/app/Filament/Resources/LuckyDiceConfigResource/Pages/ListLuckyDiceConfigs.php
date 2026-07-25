<?php

namespace App\Filament\Resources\LuckyDiceConfigResource\Pages;

use App\Filament\Resources\LuckyDiceConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLuckyDiceConfigs extends ListRecords
{
    protected static string $resource = LuckyDiceConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
