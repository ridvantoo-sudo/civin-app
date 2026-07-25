<?php

namespace App\Filament\Resources\GameLogResource\Pages;

use App\Filament\Resources\GameLogResource;
use Filament\Resources\Pages\ListRecords;

class ListGameLogs extends ListRecords
{
    protected static string $resource = GameLogResource::class;
}
