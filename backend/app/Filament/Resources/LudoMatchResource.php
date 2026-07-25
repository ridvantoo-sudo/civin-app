<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\Ludo\Models\LudoConfig;
use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LudoMatchResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LudoMatchResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LudoMatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-play';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 51;

    protected static ?string $navigationLabel = 'Ludo Matches';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->limit(8)->label('Match'),
                Tables\Columns\TextColumn::make('mode')->badge(),
                Tables\Columns\TextColumn::make('matchmaking')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('result')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('current_turn_color')->label('Turn'),
                Tables\Columns\TextColumn::make('players_count')->label('Players'),
                Tables\Columns\TextColumn::make('entry_fee_coins')->label('Fee'),
                Tables\Columns\TextColumn::make('pot_coins')->label('Pot'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(array_combine(
                    [
                        LudoMatch::STATUS_WAITING,
                        LudoMatch::STATUS_READY,
                        LudoMatch::STATUS_ACTIVE,
                        LudoMatch::STATUS_FINISHED,
                        LudoMatch::STATUS_CANCELLED,
                        LudoMatch::STATUS_FORFEITED,
                    ],
                    [
                        LudoMatch::STATUS_WAITING,
                        LudoMatch::STATUS_READY,
                        LudoMatch::STATUS_ACTIVE,
                        LudoMatch::STATUS_FINISHED,
                        LudoMatch::STATUS_CANCELLED,
                        LudoMatch::STATUS_FORFEITED,
                    ],
                )),
                Tables\Filters\SelectFilter::make('mode')->options(array_combine(LudoConfig::MODES, LudoConfig::MODES)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLudoMatches::route('/'),
        ];
    }
}
