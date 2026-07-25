<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $this->record->profile()->firstOrCreate([], [
            'display_name' => $this->record->username,
            'level' => 1,
        ]);

        $this->record->wallet()->firstOrCreate([], [
            'coins_balance' => 0,
            'diamonds_balance' => 0,
        ]);
    }
}
