<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Models\GamePlugin;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\GamePluginResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GamePluginResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = GamePlugin::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Plugins';

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
            Forms\Components\TextInput::make('version')->required()->maxLength(32),
            Forms\Components\Toggle::make('enabled')->required(),
            Forms\Components\TextInput::make('coin_limit_per_day')->numeric()->minValue(0),
            Forms\Components\TextInput::make('coin_limit_per_play')->numeric()->minValue(0),
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
            Forms\Components\Textarea::make('reward_config')
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
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\IconColumn::make('enabled')->boolean(),
                Tables\Columns\TextColumn::make('coin_limit_per_day')->label('Daily coin limit'),
                Tables\Columns\TextColumn::make('coin_limit_per_play')->label('Per-play limit'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('enabled'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (GamePlugin $record): string => $record->enabled ? 'Disable' : 'Enable')
                    ->action(function (GamePlugin $record): void {
                        $record->forceFill(['enabled' => ! $record->enabled])->save();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGamePlugins::route('/'),
            'create' => Pages\CreateGamePlugin::route('/create'),
            'edit' => Pages\EditGamePlugin::route('/{record}/edit'),
        ];
    }
}
