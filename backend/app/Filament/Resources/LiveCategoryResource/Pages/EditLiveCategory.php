<?php

namespace App\Filament\Resources\LiveCategoryResource\Pages;

use App\Filament\Resources\LiveCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLiveCategory extends EditRecord
{
    protected static string $resource = LiveCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
