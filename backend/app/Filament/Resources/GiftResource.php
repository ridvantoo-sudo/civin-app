<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Gifts\Models\Gift;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\GiftResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GiftResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = Gift::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GIFTS);
    }

    public static function canCreate(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GIFTS);
    }

    public static function canEdit($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GIFTS);
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GIFTS);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('name')->required()->maxLength(100),
                Forms\Components\TextInput::make('icon')->url()->maxLength(2048),
                Forms\Components\TextInput::make('animation_url')->url()->maxLength(2048),
                Forms\Components\TextInput::make('coin_price')->numeric()->minValue(1)->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        Gift::STATUS_ACTIVE => 'Active',
                        Gift::STATUS_INACTIVE => 'Inactive',
                    ])
                    ->required(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('Category'),
                Tables\Columns\TextColumn::make('coin_price')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => $state === Gift::STATUS_ACTIVE ? 'success' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Gift::STATUS_ACTIVE => 'Active',
                        Gift::STATUS_INACTIVE => 'Inactive',
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
            'index' => Pages\ListGifts::route('/'),
            'create' => Pages\CreateGift::route('/create'),
            'edit' => Pages\EditGift::route('/{record}/edit'),
        ];
    }
}
