<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LuckyNumberConfigResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LuckyNumberConfigResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LuckyNumberConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-hashtag';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Lucky Number Config';

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
            Forms\Components\TextInput::make('default_min_number')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('default_max_number')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_picks')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('draw_count')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('rtp_percent')->numeric()->minValue(0)->maxValue(100)->required()->label('RTP %'),
            Forms\Components\TextInput::make('house_edge_percent')->numeric()->minValue(0)->maxValue(100)->required()->label('House Edge %'),
            Forms\Components\TextInput::make('max_tickets_per_day')->numeric()->minValue(1),
            Forms\Components\TextInput::make('cooldown_seconds')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('draw_interval_seconds')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('progressive_contribution_percent')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('vip_reward_multiplier')->numeric()->minValue(1)->required(),
            Forms\Components\KeyValue::make('reward_multipliers')->columnSpanFull(),
            Forms\Components\Textarea::make('bonus_probabilities')
                ->helperText('JSON object of bonus chances and multipliers')
                ->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) ($state ?? ''))
                ->dehydrateStateUsing(function ($state) {
                    if (is_array($state)) {
                        return $state;
                    }
                    $decoded = json_decode((string) $state, true);

                    return is_array($decoded) ? $decoded : null;
                })
                ->columnSpanFull(),
            Forms\Components\Toggle::make('jackpot_enabled'),
            Forms\Components\Toggle::make('bonus_enabled'),
            Forms\Components\Select::make('status')
                ->options([
                    LuckyNumberConfig::STATUS_ACTIVE => 'Active',
                    LuckyNumberConfig::STATUS_INACTIVE => 'Inactive',
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
                Tables\Columns\TextColumn::make('default_max_number')->label('Max #'),
                Tables\Columns\TextColumn::make('rtp_percent')->label('RTP'),
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
            'index' => Pages\ListLuckyNumberConfigs::route('/'),
            'create' => Pages\CreateLuckyNumberConfig::route('/create'),
            'edit' => Pages\EditLuckyNumberConfig::route('/{record}/edit'),
        ];
    }
}
