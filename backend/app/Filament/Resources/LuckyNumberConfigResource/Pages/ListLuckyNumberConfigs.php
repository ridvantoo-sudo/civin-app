<?php

namespace App\Filament\Resources\LuckyNumberConfigResource\Pages;

use App\Filament\Resources\LuckyNumberConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLuckyNumberConfigs extends ListRecords
{
    protected static string $resource = LuckyNumberConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
