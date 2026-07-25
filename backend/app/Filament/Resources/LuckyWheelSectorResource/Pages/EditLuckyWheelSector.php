<?php

namespace App\Filament\Resources\LuckyWheelSectorResource\Pages;

use App\Filament\Resources\LuckyWheelSectorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLuckyWheelSector extends EditRecord
{
    protected static string $resource = LuckyWheelSectorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
