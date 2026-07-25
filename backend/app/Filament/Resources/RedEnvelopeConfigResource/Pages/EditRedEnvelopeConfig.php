<?php

namespace App\Filament\Resources\RedEnvelopeConfigResource\Pages;

use App\Filament\Resources\RedEnvelopeConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRedEnvelopeConfig extends EditRecord
{
    protected static string $resource = RedEnvelopeConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
