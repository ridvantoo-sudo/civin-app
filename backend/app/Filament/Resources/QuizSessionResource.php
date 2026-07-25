<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\QuizSessionResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuizSessionResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = QuizSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-play';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 33;

    protected static ?string $navigationLabel = 'Quiz Sessions';

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
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->limit(8),
            Tables\Columns\TextColumn::make('mode')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('difficulty'),
            Tables\Columns\TextColumn::make('player_count'),
            Tables\Columns\TextColumn::make('reward_type'),
            Tables\Columns\TextColumn::make('created_at')->dateTime(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuizSessions::route('/'),
        ];
    }
}
