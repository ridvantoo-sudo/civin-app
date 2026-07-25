<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\RpsMatchResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RpsMatchResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = RpsMatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-play';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 41;

    protected static ?string $navigationLabel = 'RPS Matches';

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
                Tables\Columns\TextColumn::make('challenger_score')->label('C'),
                Tables\Columns\TextColumn::make('opponent_score')->label('O'),
                Tables\Columns\TextColumn::make('entry_fee_coins')->label('Fee'),
                Tables\Columns\TextColumn::make('pot_coins')->label('Pot'),
                Tables\Columns\IconColumn::make('is_vip_arena')->boolean()->label('VIP'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(array_combine(
                    [
                        RpsMatch::STATUS_PENDING,
                        RpsMatch::STATUS_MATCHING,
                        RpsMatch::STATUS_ACTIVE,
                        RpsMatch::STATUS_FINISHED,
                        RpsMatch::STATUS_EXPIRED,
                        RpsMatch::STATUS_CANCELLED,
                    ],
                    [
                        RpsMatch::STATUS_PENDING,
                        RpsMatch::STATUS_MATCHING,
                        RpsMatch::STATUS_ACTIVE,
                        RpsMatch::STATUS_FINISHED,
                        RpsMatch::STATUS_EXPIRED,
                        RpsMatch::STATUS_CANCELLED,
                    ],
                )),
                Tables\Filters\SelectFilter::make('mode')->options(array_combine(RpsConfig::MODES, RpsConfig::MODES)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRpsMatches::route('/'),
        ];
    }
}
