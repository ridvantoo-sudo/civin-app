<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Plugins\TreasureBox\Models\TreasureBoxConfig;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\TreasureBoxConfigResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TreasureBoxConfigResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = TreasureBoxConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 33;

    protected static ?string $navigationLabel = 'Treasure Box Config';

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
            Forms\Components\TextInput::make('min_coins_amount')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_coins_amount')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('min_diamonds_amount')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_diamonds_amount')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_winners')->numeric()->minValue(1)->maxValue(200)->required(),
            Forms\Components\TextInput::make('min_countdown_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_countdown_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('default_countdown_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('min_claim_duration_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_claim_duration_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('default_claim_duration_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_created_per_day')
                ->numeric()
                ->minValue(1)
                ->helperText('Leave empty for no daily limit.'),
            Forms\Components\Toggle::make('refund_unclaimed')
                ->helperText('Return remaining value to the creator when the timer expires.'),
            Forms\Components\CheckboxList::make('allowed_reward_types')
                ->options(array_combine(
                    TreasureBox::REWARD_TYPES,
                    ['Coins', 'Diamonds', 'Gift', 'Frame', 'Entry effect', 'VIP days', 'SVIP days', 'Mystery', 'Jackpot'],
                ))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('allowed_types')
                ->options(array_combine(
                    TreasureBox::TYPES,
                    ['Instant', 'Scheduled', 'Auto', 'Live room', 'Voice room'],
                ))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('allowed_visibilities')
                ->options(array_combine(TreasureBox::VISIBILITIES, ['Public', 'Private', 'Live room', 'Voice room']))
                ->columns(2)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('allowed_eligibilities')
                ->options(array_combine(
                    TreasureBox::ELIGIBILITY_SCOPES,
                    ['Everyone', 'Followers', 'VIP', 'SVIP', 'Family', 'Agency', 'Min watch time', 'Min user level', 'Min gift value', 'Whitelist'],
                ))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('allowed_room_types')
                ->options(array_combine(TreasureBox::ROOM_TYPES, ['Live', 'Voice', 'Chat', 'Family']))
                ->columns(4)
                ->columnSpanFull(),
            Forms\Components\Repeater::make('auto_rules')
                ->label('Auto Treasure Rules')
                ->helperText('Scheduler creates TYPE_AUTO treasures from funded sponsor accounts.')
                ->schema([
                    Forms\Components\TextInput::make('id')->required()->maxLength(64),
                    Forms\Components\Toggle::make('enabled')->default(true),
                    Forms\Components\TextInput::make('creator_user_id')
                        ->label('Sponsor user UUID')
                        ->required()
                        ->maxLength(36),
                    Forms\Components\TextInput::make('interval_minutes')->numeric()->minValue(1)->default(60),
                    Forms\Components\Select::make('reward_type')
                        ->options(array_combine(TreasureBox::REWARD_TYPES, TreasureBox::REWARD_TYPES))
                        ->default(TreasureBox::REWARD_COINS)
                        ->required(),
                    Forms\Components\TextInput::make('total_amount')->numeric()->minValue(1)->required(),
                    Forms\Components\TextInput::make('winner_count')->numeric()->minValue(1)->default(1)->required(),
                    Forms\Components\TextInput::make('countdown_seconds')->numeric()->minValue(1)->default(30),
                    Forms\Components\TextInput::make('claim_duration_seconds')->numeric()->minValue(1)->default(300),
                    Forms\Components\Select::make('eligibility_scope')
                        ->options(array_combine(TreasureBox::ELIGIBILITY_SCOPES, TreasureBox::ELIGIBILITY_SCOPES))
                        ->default(TreasureBox::SCOPE_EVERYONE),
                    Forms\Components\Select::make('visibility')
                        ->options(array_combine(TreasureBox::VISIBILITIES, TreasureBox::VISIBILITIES))
                        ->default(TreasureBox::VIS_PUBLIC),
                    Forms\Components\Toggle::make('auto_start')->default(true),
                    Forms\Components\TextInput::make('message')->maxLength(200),
                ])
                ->collapsed()
                ->columnSpanFull()
                ->default([]),
            Forms\Components\Toggle::make('enabled'),
            Forms\Components\Select::make('status')
                ->options([
                    TreasureBoxConfig::STATUS_ACTIVE => 'Active',
                    TreasureBoxConfig::STATUS_INACTIVE => 'Inactive',
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
                Tables\Columns\TextColumn::make('min_coins_amount')->label('Min coins'),
                Tables\Columns\TextColumn::make('max_coins_amount')->label('Max coins'),
                Tables\Columns\TextColumn::make('max_winners')->label('Max winners'),
                Tables\Columns\TextColumn::make('default_countdown_seconds')->label('Countdown'),
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\IconColumn::make('refund_unclaimed')->boolean(),
                Tables\Columns\IconColumn::make('enabled')->boolean(),
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
            'index' => Pages\ListTreasureBoxConfigs::route('/'),
            'create' => Pages\CreateTreasureBoxConfig::route('/create'),
            'edit' => Pages\EditTreasureBoxConfig::route('/{record}/edit'),
        ];
    }
}
