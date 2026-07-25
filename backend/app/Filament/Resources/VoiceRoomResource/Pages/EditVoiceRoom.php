<?php

namespace App\Filament\Resources\VoiceRoomResource\Pages;

use App\Filament\Resources\VoiceRoomResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVoiceRoom extends EditRecord
{
    protected static string $resource = VoiceRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
