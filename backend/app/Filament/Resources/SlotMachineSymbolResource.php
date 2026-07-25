<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSymbol;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\SlotMachineSymbolResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SlotMachineSymbolResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = SlotMachineSymbol::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Slot Symbols';

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
            Forms\Components\TextInput::make('weight')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('payout_3')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('payout_4')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('payout_5')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('color')->maxLength(16),
            Forms\Components\TextInput::make('icon')->maxLength(2048),
            Forms\Components\Toggle::make('is_wild'),
            Forms\Components\Toggle::make('is_scatter'),
            Forms\Components\Toggle::make('is_bonus'),
            Forms\Components\Toggle::make('is_jackpot'),
            Forms\Components\Toggle::make('is_multiplier'),
            Forms\Components\TextInput::make('multiplier_value')->numeric()->minValue(1),
            Forms\Components\TextInput::make('sort_order')->numeric()->minValue(0),
            Forms\Components\Select::make('status')->options([
                SlotMachineSymbol::STATUS_ACTIVE => 'Active',
                SlotMachineSymbol::STATUS_INACTIVE => 'Inactive',
            ])->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('label')->searchable(),
            Tables\Columns\TextColumn::make('code'),
            Tables\Columns\TextColumn::make('config.name')->label('Config'),
            Tables\Columns\TextColumn::make('weight'),
            Tables\Columns\IconColumn::make('is_wild')->boolean(),
            Tables\Columns\IconColumn::make('is_scatter')->boolean(),
            Tables\Columns\IconColumn::make('is_jackpot')->boolean(),
            Tables\Columns\TextColumn::make('status')->badge(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSlotMachineSymbols::route('/'),
            'create' => Pages\CreateSlotMachineSymbol::route('/create'),
            'edit' => Pages\EditSlotMachineSymbol::route('/{record}/edit'),
        ];
    }
}
