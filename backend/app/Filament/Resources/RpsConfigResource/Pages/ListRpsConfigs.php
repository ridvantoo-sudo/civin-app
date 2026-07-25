<?php

namespace App\Filament\Resources\RpsConfigResource\Pages;

use App\Filament\Resources\RpsConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRpsConfigs extends ListRecords
{
    protected static string $resource = RpsConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
