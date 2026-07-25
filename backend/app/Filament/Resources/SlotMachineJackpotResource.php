<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineJackpot;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\SlotMachineJackpotResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SlotMachineJackpotResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = SlotMachineJackpot::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 23;

    protected static ?string $navigationLabel = 'Slot Jackpot Manager';

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
            Forms\Components\Select::make('type')->options([
                SlotMachineJackpot::TYPE_ROOM => 'Room',
                SlotMachineJackpot::TYPE_GLOBAL => 'Global',
                SlotMachineJackpot::TYPE_DAILY => 'Daily',
                SlotMachineJackpot::TYPE_VIP => 'VIP',
                SlotMachineJackpot::TYPE_PROGRESSIVE => 'Progressive',
            ])->required(),
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\TextInput::make('pool_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('seed_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('min_pool_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('contribution_percent')->numeric()->minValue(0)->required(),
            Forms\Components\Toggle::make('requires_vip'),
            Forms\Components\TextInput::make('room_id')->maxLength(64),
            Forms\Components\DatePicker::make('daily_date'),
            Forms\Components\Select::make('status')->options([
                SlotMachineJackpot::STATUS_ACTIVE => 'Active',
                SlotMachineJackpot::STATUS_INACTIVE => 'Inactive',
            ])->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('pool_coins')->label('Pool'),
            Tables\Columns\TextColumn::make('contribution_percent')->label('Contrib %'),
            Tables\Columns\IconColumn::make('requires_vip')->boolean(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('last_won_at')->dateTime(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSlotMachineJackpots::route('/'),
            'create' => Pages\CreateSlotMachineJackpot::route('/create'),
            'edit' => Pages\EditSlotMachineJackpot::route('/{record}/edit'),
        ];
    }
}
