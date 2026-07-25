<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelConfig;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LuckyWheelConfigResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LuckyWheelConfigResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LuckyWheelConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Lucky Wheel Config';

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
            Forms\Components\TextInput::make('slug')->required()->maxLength(64)->disabledOn('edit'),
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\Textarea::make('description')->maxLength(500)->columnSpanFull(),
            Forms\Components\TextInput::make('version')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('spin_cost_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('daily_free_spins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('max_spins_per_day')->numeric()->minValue(1),
            Forms\Components\TextInput::make('cooldown_seconds')->numeric()->minValue(0)->required(),
            Forms\Components\Toggle::make('jackpot_enabled'),
            Forms\Components\TextInput::make('jackpot_coins')->numeric()->minValue(0),
            Forms\Components\Select::make('status')
                ->options([
                    LuckyWheelConfig::STATUS_ACTIVE => 'Active',
                    LuckyWheelConfig::STATUS_INACTIVE => 'Inactive',
                ])
                ->required(),
            Forms\Components\Toggle::make('is_default'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug'),
                Tables\Columns\TextColumn::make('spin_cost_coins')->label('Cost'),
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLuckyWheelConfigs::route('/'),
            'create' => Pages\CreateLuckyWheelConfig::route('/create'),
            'edit' => Pages\EditLuckyWheelConfig::route('/{record}/edit'),
        ];
    }
}
