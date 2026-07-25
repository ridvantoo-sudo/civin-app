<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\Ludo\Models\LudoConfig;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LudoConfigResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LudoConfigResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LudoConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Ludo Config';

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
            Forms\Components\TextInput::make('ready_timeout_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('turn_timeout_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('match_timeout_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('forfeit_after_afk')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('house_rake_bps')->numeric()->minValue(0)->maxValue(10000)->required(),
            Forms\Components\CheckboxList::make('modes_enabled')
                ->options(array_combine(LudoConfig::MODES, LudoConfig::MODES))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('matchmaking_enabled')
                ->options(array_combine(LudoConfig::MATCHMAKING, LudoConfig::MATCHMAKING))
                ->columns(4)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('tournament_formats_enabled')
                ->options(array_combine(LudoConfig::TOURNAMENT_FORMATS, LudoConfig::TOURNAMENT_FORMATS))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('rules')
                ->rows(8)
                ->helperText('JSON rule set for classic Ludo variants')
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
            'index' => Pages\ListLudoConfigs::route('/'),
            'create' => Pages\CreateLudoConfig::route('/create'),
            'edit' => Pages\EditLudoConfig::route('/{record}/edit'),
        ];
    }
}
