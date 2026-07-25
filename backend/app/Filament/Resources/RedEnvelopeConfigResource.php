<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Plugins\RedEnvelope\Models\RedEnvelopeConfig;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\RedEnvelopeConfigResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RedEnvelopeConfigResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = RedEnvelopeConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Red Envelope Config';

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
            Forms\Components\TextInput::make('max_winners')
                ->numeric()
                ->minValue(1)
                ->maxValue(200)
                ->required()
                ->helperText('Hard capped at 200 by the legacy slots column.'),
            Forms\Components\TextInput::make('min_duration_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_duration_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('default_duration_seconds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_created_per_day')
                ->numeric()
                ->minValue(1)
                ->helperText('Leave empty for no daily limit.'),
            Forms\Components\Toggle::make('refund_unclaimed')
                ->helperText('Return unclaimed value to the creator when the timer expires.'),
            Forms\Components\CheckboxList::make('allowed_reward_types')
                ->options(array_combine(RedEnvelope::REWARD_TYPES, ['Coins', 'Diamonds']))
                ->columns(2)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('allowed_distribution_types')
                ->options(array_combine(RedEnvelope::DISTRIBUTION_TYPES, ['Random', 'Fixed']))
                ->columns(2)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('allowed_visibilities')
                ->options(array_combine(RedEnvelope::VISIBILITIES, ['Public', 'Private', 'Live room', 'Voice room']))
                ->columns(2)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('allowed_claim_scopes')
                ->options(array_combine(
                    RedEnvelope::CLAIM_SCOPES,
                    ['Everyone', 'Followers', 'VIP', 'Family', 'Agency', 'Whitelist'],
                ))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('allowed_room_types')
                ->options(array_combine(RedEnvelope::ROOM_TYPES, ['Live', 'Voice', 'Chat', 'Family']))
                ->columns(4)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('enabled'),
            Forms\Components\Select::make('status')
                ->options([
                    RedEnvelopeConfig::STATUS_ACTIVE => 'Active',
                    RedEnvelopeConfig::STATUS_INACTIVE => 'Inactive',
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
            'index' => Pages\ListRedEnvelopeConfigs::route('/'),
            'create' => Pages\CreateRedEnvelopeConfig::route('/create'),
            'edit' => Pages\EditRedEnvelopeConfig::route('/{record}/edit'),
        ];
    }
}
