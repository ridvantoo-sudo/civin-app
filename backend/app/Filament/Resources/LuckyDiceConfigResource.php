<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceConfig;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LuckyDiceConfigResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LuckyDiceConfigResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LuckyDiceConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Lucky Dice Config';

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
            Forms\Components\TextInput::make('min_bet_coins')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_bet_coins')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('default_bet_coins')->numeric()->minValue(1)->required(),
            Forms\Components\TagsInput::make('chip_amounts')
                ->label('Chip amounts')
                ->suggestions([50, 100, 200, 500, 1000])
                ->separator(',')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('max_plays_per_day')->numeric()->minValue(1),
            Forms\Components\TextInput::make('cooldown_seconds')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('countdown_seconds')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('multiplier_big_small')->numeric()->minValue(0)->step(0.01)->required(),
            Forms\Components\TextInput::make('multiplier_odd_even')->numeric()->minValue(0)->step(0.01)->required(),
            Forms\Components\TextInput::make('multiplier_triple')->numeric()->minValue(0)->step(0.01)->required(),
            Forms\Components\KeyValue::make('exact_total_multipliers')
                ->label('Exact total multipliers')
                ->keyLabel('Total')
                ->valueLabel('Multiplier')
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('modes_enabled')
                ->options([
                    LuckyDiceConfig::MODE_BIG_SMALL => 'Big / Small',
                    LuckyDiceConfig::MODE_ODD_EVEN => 'Odd / Even',
                    LuckyDiceConfig::MODE_EXACT_TOTAL => 'Exact Total',
                    LuckyDiceConfig::MODE_TRIPLE_MATCH => 'Triple Match',
                ])
                ->columns(2)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('jackpot_enabled'),
            Forms\Components\TextInput::make('jackpot_coins')->numeric()->minValue(0),
            Forms\Components\Toggle::make('jackpot_on_triple'),
            Forms\Components\Select::make('status')
                ->options([
                    LuckyDiceConfig::STATUS_ACTIVE => 'Active',
                    LuckyDiceConfig::STATUS_INACTIVE => 'Inactive',
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
                Tables\Columns\TextColumn::make('min_bet_coins')->label('Min'),
                Tables\Columns\TextColumn::make('max_bet_coins')->label('Max'),
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\IconColumn::make('jackpot_enabled')->boolean(),
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
            'index' => Pages\ListLuckyDiceConfigs::route('/'),
            'create' => Pages\CreateLuckyDiceConfig::route('/create'),
            'edit' => Pages\EditLuckyDiceConfig::route('/{record}/edit'),
        ];
    }
}
