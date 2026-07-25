<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Marketplace\Models\MarketItem;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\MarketItemResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MarketItemResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = MarketItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Marketplace';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_MARKETPLACE);
    }

    public static function canCreate(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_MARKETPLACE);
    }

    public static function canEdit($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_MARKETPLACE);
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_MARKETPLACE);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        MarketItem::TYPE_FRAME => 'Frame',
                        MarketItem::TYPE_ENTRY_EFFECT => 'Entry Effect',
                        MarketItem::TYPE_CHAT_BUBBLE => 'Chat Bubble',
                        MarketItem::TYPE_PROFILE_DECORATION => 'Profile Decoration',
                        MarketItem::TYPE_SPECIAL_USER_ID => 'Special User ID',
                        MarketItem::TYPE_PROFILE_THEME => 'Profile Theme',
                        MarketItem::TYPE_STICKER_PACK => 'Sticker Pack',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('name')->required()->maxLength(120),
                Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('icon_url')->url()->maxLength(2048),
                Forms\Components\TextInput::make('asset_url')->url()->maxLength(2048),
                Forms\Components\TextInput::make('coin_price')->numeric()->minValue(1)->required(),
                Forms\Components\TextInput::make('duration_days')->numeric()->minValue(1)
                    ->helperText('Leave empty for permanent items.'),
                Forms\Components\Toggle::make('is_limited')->default(false),
                Forms\Components\TextInput::make('stock_total')->numeric()->minValue(0),
                Forms\Components\TextInput::make('stock_remaining')->numeric()->minValue(0),
                Forms\Components\TextInput::make('special_value')->maxLength(64)
                    ->helperText('Required for special user IDs.'),
                Forms\Components\Select::make('membership_required')
                    ->options([
                        'vip' => 'VIP',
                        'svip' => 'SVIP',
                    ])
                    ->nullable(),
                Forms\Components\Select::make('status')
                    ->options([
                        MarketItem::STATUS_ACTIVE => 'Active',
                        MarketItem::STATUS_INACTIVE => 'Inactive',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                Forms\Components\DateTimePicker::make('starts_at'),
                Forms\Components\DateTimePicker::make('ends_at'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('coin_price')->numeric()->sortable(),
                Tables\Columns\IconColumn::make('is_limited')->boolean(),
                Tables\Columns\TextColumn::make('stock_remaining')->label('Stock'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => $state === MarketItem::STATUS_ACTIVE ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        MarketItem::TYPE_FRAME => 'Frame',
                        MarketItem::TYPE_ENTRY_EFFECT => 'Entry Effect',
                        MarketItem::TYPE_CHAT_BUBBLE => 'Chat Bubble',
                        MarketItem::TYPE_PROFILE_DECORATION => 'Profile Decorations',
                        MarketItem::TYPE_SPECIAL_USER_ID => 'Special User ID',
                        MarketItem::TYPE_PROFILE_THEME => 'Profile Theme',
                        MarketItem::TYPE_STICKER_PACK => 'Sticker Pack',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        MarketItem::STATUS_ACTIVE => 'Active',
                        MarketItem::STATUS_INACTIVE => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListMarketItems::route('/'),
            'create' => Pages\CreateMarketItem::route('/create'),
            'edit' => Pages\EditMarketItem::route('/{record}/edit'),
        ];
    }
}
