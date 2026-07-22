<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Vip\Models\VipLevel;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\VipLevelResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VipLevelResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = VipLevel::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_VIP);
    }

    public static function canCreate(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_VIP);
    }

    public static function canEdit($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_VIP);
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_VIP);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(100),
                Forms\Components\TextInput::make('level')->numeric()->minValue(1)->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('coin_price')->numeric()->minValue(0)->required(),
                Forms\Components\TextInput::make('duration_days')->numeric()->minValue(1)->required(),
                Forms\Components\TextInput::make('badge')->url()->maxLength(2048),
                Forms\Components\TextInput::make('profile_frame')->url()->maxLength(2048),
                Forms\Components\TextInput::make('chat_effect')->url()->maxLength(2048),
                Forms\Components\TextInput::make('entrance_animation')->url()->maxLength(2048),
                Forms\Components\Toggle::make('exclusive_gifts'),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                Forms\Components\Select::make('status')
                    ->options([
                        VipLevel::STATUS_ACTIVE => 'Active',
                        VipLevel::STATUS_INACTIVE => 'Inactive',
                    ])
                    ->required(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('level')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('coin_price')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('duration_days')->numeric(),
                Tables\Columns\IconColumn::make('exclusive_gifts')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
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
            'index' => Pages\ListVipLevels::route('/'),
            'create' => Pages\CreateVipLevel::route('/create'),
            'edit' => Pages\EditVipLevel::route('/{record}/edit'),
        ];
    }
}
