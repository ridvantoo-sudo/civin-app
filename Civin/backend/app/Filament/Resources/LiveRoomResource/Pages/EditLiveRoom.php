<?php

namespace App\Filament\Resources\LiveRoomResource\Pages;

use App\Filament\Resources\LiveRoomResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLiveRoom extends EditRecord
{
    protected static string $resource = LiveRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
