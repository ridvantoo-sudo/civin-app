<?php

namespace App\Filament\Resources\LudoTournamentResource\Pages;

use App\Filament\Resources\LudoTournamentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLudoTournaments extends ListRecords
{
    protected static string $resource = LudoTournamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
