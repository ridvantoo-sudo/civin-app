<?php

namespace App\Filament\Resources\QuizConfigResource\Pages;

use App\Filament\Resources\QuizConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQuizConfigs extends ListRecords
{
    protected static string $resource = QuizConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
