<?php

namespace App\Filament\Resources\QuizTournamentResource\Pages;

use App\Filament\Resources\QuizTournamentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQuizTournaments extends ListRecords
{
    protected static string $resource = QuizTournamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
