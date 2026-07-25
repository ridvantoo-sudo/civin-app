<?php

namespace App\Filament\Resources\RpsTournamentResource\Pages;

use App\Filament\Resources\RpsTournamentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRpsTournaments extends ListRecords
{
    protected static string $resource = RpsTournamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
