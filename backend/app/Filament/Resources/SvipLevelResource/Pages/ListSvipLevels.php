<?php

namespace App\Filament\Resources\SvipLevelResource\Pages;

use App\Filament\Resources\SvipLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSvipLevels extends ListRecords
{
    protected static string $resource = SvipLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
