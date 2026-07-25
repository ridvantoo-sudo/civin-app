<?php

namespace App\Filament\Resources\SvipLevelResource\Pages;

use App\Filament\Resources\SvipLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSvipLevel extends EditRecord
{
    protected static string $resource = SvipLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
