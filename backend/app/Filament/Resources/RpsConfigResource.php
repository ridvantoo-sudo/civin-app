<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\RpsConfigResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RpsConfigResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = RpsConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-hand-raised';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'RPS Config';

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
            Forms\Components\TextInput::make('min_entry_fee_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('max_entry_fee_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('default_entry_fee_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('default_round_timeout_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('min_round_timeout_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_round_timeout_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('accept_timeout_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('match_timeout_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_rounds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('forfeit_after_missed_rounds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('rematch_limit')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('max_created_per_day')->numeric()->minValue(1)->nullable(),
            Forms\Components\TextInput::make('house_rake_bps')->numeric()->minValue(0)->maxValue(10000)->required(),
            Forms\Components\TextInput::make('vip_reward_multiplier')->numeric()->minValue(1)->required(),
            Forms\Components\CheckboxList::make('modes_enabled')
                ->options(array_combine(RpsConfig::MODES, RpsConfig::MODES))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('matchmaking_enabled')
                ->options(array_combine(RpsConfig::MATCHMAKING, RpsConfig::MATCHMAKING))
                ->columns(4)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('reward_types_enabled')
                ->options(array_combine(RpsConfig::REWARD_TYPES, RpsConfig::REWARD_TYPES))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('tournament_formats_enabled')
                ->options(array_combine(RpsConfig::TOURNAMENT_FORMATS, RpsConfig::TOURNAMENT_FORMATS))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\KeyValue::make('mode_rounds')
                ->keyLabel('Mode')
                ->valueLabel('Best of')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('move_rules')
                ->rows(8)
                ->helperText('JSON rule set: {"moves": [...], "beats": {"move": ["loses_to_this"]}}')
                ->formatStateUsing(fn ($state): string => is_array($state)
                    ? (json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                    : (string) $state)
                ->dehydrateStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('enabled')->required(),
            Forms\Components\Toggle::make('is_default'),
            Forms\Components\Select::make('status')->options([
                'active' => 'Active',
                'inactive' => 'Inactive',
            ])->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('default_entry_fee_coins')->label('Default fee'),
                Tables\Columns\IconColumn::make('enabled')->boolean(),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRpsConfigs::route('/'),
            'create' => Pages\CreateRpsConfig::route('/create'),
            'edit' => Pages\EditRpsConfig::route('/{record}/edit'),
        ];
    }
}
