<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Models\Game;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\GameResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GameResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = Game::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 1;

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
            Forms\Components\TextInput::make('icon')->url()->maxLength(2048),
            Forms\Components\TextInput::make('category')->maxLength(32),
            Forms\Components\TextInput::make('min_bet_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('max_bet_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('house_edge_bps')->numeric()->minValue(0)->required(),
            Forms\Components\Toggle::make('supports_multiplayer'),
            Forms\Components\Select::make('status')
                ->options([
                    Game::STATUS_ACTIVE => 'Active',
                    Game::STATUS_INACTIVE => 'Inactive',
                ])
                ->required(),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Textarea::make('config')
                ->formatStateUsing(fn ($state): string => $state == null ? '' : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?? '')
                ->dehydrateStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return null;
                    }
                    $decoded = json_decode((string) $state, true);

                    return is_array($decoded) ? $decoded : null;
                })
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('min_bet_coins')->numeric()->label('Min bet'),
                Tables\Columns\TextColumn::make('max_bet_coins')->numeric()->label('Max bet'),
                Tables\Columns\IconColumn::make('supports_multiplayer')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => $state === Game::STATUS_ACTIVE ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Game::STATUS_ACTIVE => 'Active',
                        Game::STATUS_INACTIVE => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }
}
