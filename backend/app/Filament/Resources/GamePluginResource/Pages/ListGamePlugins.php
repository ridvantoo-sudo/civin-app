<?php

namespace App\Filament\Resources\GamePluginResource\Pages;

use App\Filament\Resources\GamePluginResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGamePlugins extends ListRecords
{
    protected static string $resource = GamePluginResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
