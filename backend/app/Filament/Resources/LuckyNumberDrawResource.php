<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberDraw;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LuckyNumberDrawResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LuckyNumberDrawResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LuckyNumberDraw::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 23;

    protected static ?string $navigationLabel = 'Lucky Number Draws';

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
                Tables\Columns\TextColumn::make('id')->limit(8)->label('Draw'),
                Tables\Columns\TextColumn::make('draw_type')->badge(),
                Tables\Columns\TextColumn::make('game_mode')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('min_number')->label('Min'),
                Tables\Columns\TextColumn::make('max_number')->label('Max'),
                Tables\Columns\TextColumn::make('draw_count')->label('Count'),
                Tables\Columns\TextColumn::make('scheduled_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('finished_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    LuckyNumberDraw::STATUS_PENDING => 'Pending',
                    LuckyNumberDraw::STATUS_STARTED => 'Started',
                    LuckyNumberDraw::STATUS_FINISHED => 'Finished',
                    LuckyNumberDraw::STATUS_CANCELLED => 'Cancelled',
                ]),
                Tables\Filters\SelectFilter::make('draw_type')->options([
                    LuckyNumberDraw::TYPE_INSTANT => 'Instant',
                    LuckyNumberDraw::TYPE_SCHEDULED => 'Scheduled',
                    LuckyNumberDraw::TYPE_MANUAL => 'Manual',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLuckyNumberDraws::route('/'),
        ];
    }
}
