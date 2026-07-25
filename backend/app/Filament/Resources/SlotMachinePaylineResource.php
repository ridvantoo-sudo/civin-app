<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachinePayline;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\SlotMachinePaylineResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SlotMachinePaylineResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = SlotMachinePayline::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 22;

    protected static ?string $navigationLabel = 'Slot Paylines';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function canCreate(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function canEdit($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('config_id')->relationship('config', 'name')->required(),
            Forms\Components\TextInput::make('code')->required()->maxLength(32),
            Forms\Components\TextInput::make('label')->required()->maxLength(64),
            Forms\Components\Select::make('layout')->options([
                SlotMachinePayline::LAYOUT_CLASSIC => 'Classic',
                SlotMachinePayline::LAYOUT_PREMIUM => 'Premium',
            ])->required(),
            Forms\Components\Textarea::make('positions')
                ->required()
                ->helperText('JSON array of [col,row] pairs')
                ->formatStateUsing(fn ($state): string => is_string($state) ? $state : json_encode($state ?? [], JSON_PRETTY_PRINT))
                ->dehydrateStateUsing(function ($state) {
                    if (is_array($state)) {
                        return $state;
                    }

                    $decoded = json_decode((string) $state, true);

                    return is_array($decoded) ? $decoded : [];
                })
                ->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->numeric()->minValue(0),
            Forms\Components\Select::make('status')->options([
                SlotMachinePayline::STATUS_ACTIVE => 'Active',
                SlotMachinePayline::STATUS_INACTIVE => 'Inactive',
            ])->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('label')->searchable(),
            Tables\Columns\TextColumn::make('code'),
            Tables\Columns\TextColumn::make('layout'),
            Tables\Columns\TextColumn::make('config.name')->label('Config'),
            Tables\Columns\TextColumn::make('status')->badge(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSlotMachinePaylines::route('/'),
            'create' => Pages\CreateSlotMachinePayline::route('/create'),
            'edit' => Pages\EditSlotMachinePayline::route('/{record}/edit'),
        ];
    }
}
