<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\Ludo\Models\LudoConfig;
use App\Features\Games\Plugins\Ludo\Models\LudoTournament;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LudoTournamentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LudoTournamentResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LudoTournament::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 52;

    protected static ?string $navigationLabel = 'Ludo Tournaments';

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
            Forms\Components\TextInput::make('slug')->required()->maxLength(80)->disabledOn('edit'),
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\Textarea::make('description')->maxLength(1000)->columnSpanFull(),
            Forms\Components\Select::make('format')
                ->options(array_combine(LudoConfig::TOURNAMENT_FORMATS, LudoConfig::TOURNAMENT_FORMATS))
                ->required(),
            Forms\Components\Select::make('status')->options([
                'scheduled' => 'Scheduled',
                'registration' => 'Registration',
                'active' => 'Active',
                'finished' => 'Finished',
                'cancelled' => 'Cancelled',
            ])->required(),
            Forms\Components\Select::make('mode')
                ->options(array_combine(LudoConfig::MODES, LudoConfig::MODES))
                ->required(),
            Forms\Components\TextInput::make('max_players')->numeric()->minValue(2)->required(),
            Forms\Components\TextInput::make('min_players')->numeric()->minValue(2)->required(),
            Forms\Components\TextInput::make('entry_fee_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('reward_pool')->numeric()->minValue(0)->required(),
            Forms\Components\Toggle::make('is_daily'),
            Forms\Components\Toggle::make('is_scheduled'),
            Forms\Components\DateTimePicker::make('registration_opens_at'),
            Forms\Components\DateTimePicker::make('registration_closes_at'),
            Forms\Components\DateTimePicker::make('scheduled_at'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('format')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('players_count')->label('Players'),
                Tables\Columns\TextColumn::make('max_players')->label('Cap'),
                Tables\Columns\TextColumn::make('entry_fee_coins')->label('Fee'),
                Tables\Columns\TextColumn::make('reward_pool')->label('Pool'),
                Tables\Columns\IconColumn::make('is_daily')->boolean(),
                Tables\Columns\TextColumn::make('scheduled_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('scheduled_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLudoTournaments::route('/'),
            'create' => Pages\CreateLudoTournament::route('/create'),
            'edit' => Pages\EditLudoTournament::route('/{record}/edit'),
        ];
    }
}
