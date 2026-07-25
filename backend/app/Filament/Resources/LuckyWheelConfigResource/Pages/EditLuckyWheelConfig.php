<?php

namespace App\Filament\Resources\LuckyWheelConfigResource\Pages;

use App\Filament\Resources\LuckyWheelConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLuckyWheelConfig extends EditRecord
{
    protected static string $resource = LuckyWheelConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
