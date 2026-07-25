<?php

namespace App\Filament\Resources\RedEnvelopeConfigResource\Pages;

use App\Filament\Resources\RedEnvelopeConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRedEnvelopeConfigs extends ListRecords
{
    protected static string $resource = RedEnvelopeConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
