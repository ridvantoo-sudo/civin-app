<?php

namespace App\Filament\Resources\LuckyDiceConfigResource\Pages;

use App\Filament\Resources\LuckyDiceConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLuckyDiceConfig extends EditRecord
{
    protected static string $resource = LuckyDiceConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
