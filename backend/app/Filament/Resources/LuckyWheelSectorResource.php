<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSector;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LuckyWheelSectorResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LuckyWheelSectorResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LuckyWheelSector::class;

    protected static ?string $navigationIcon = 'heroicon-o-stop';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Lucky Wheel Sectors';

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
            Forms\Components\Select::make('config_id')
                ->relationship('config', 'name')
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('position')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('label')->required()->maxLength(64),
            Forms\Components\Select::make('reward_type')
                ->options([
                    LuckyWheelSector::REWARD_TYPE_NONE => 'None',
                    LuckyWheelSector::REWARD_TYPE_COINS => 'Coins',
                    LuckyWheelSector::REWARD_TYPE_DIAMONDS => 'Diamonds',
                    LuckyWheelSector::REWARD_TYPE_ITEM => 'Item',
                    LuckyWheelSector::REWARD_TYPE_GIFT => 'Gift',
                    LuckyWheelSector::REWARD_TYPE_VIP => 'VIP',
                    LuckyWheelSector::REWARD_TYPE_CUSTOM => 'Custom',
                ])
                ->required(),
            Forms\Components\TextInput::make('reward_amount')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('reward_reference')->maxLength(64),
            Forms\Components\TextInput::make('weight')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('color')->maxLength(16),
            Forms\Components\Toggle::make('is_jackpot'),
            Forms\Components\Toggle::make('requires_fulfillment'),
            Forms\Components\Select::make('status')
                ->options([
                    LuckyWheelSector::STATUS_ACTIVE => 'Active',
                    LuckyWheelSector::STATUS_INACTIVE => 'Inactive',
                ])
                ->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('config.name')->label('Config'),
                Tables\Columns\TextColumn::make('position')->sortable(),
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('reward_type'),
                Tables\Columns\TextColumn::make('reward_amount'),
                Tables\Columns\TextColumn::make('weight'),
                Tables\Columns\IconColumn::make('is_jackpot')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->defaultSort('position')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLuckyWheelSectors::route('/'),
            'create' => Pages\CreateLuckyWheelSector::route('/create'),
            'edit' => Pages\EditLuckyWheelSector::route('/{record}/edit'),
        ];
    }
}
