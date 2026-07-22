<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Profiles\Models\Profile;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\ProfileResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProfileResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = Profile::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Community';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_USERS);
    }

    public static function canCreate(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_USERS);
    }

    public static function canEdit($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_USERS);
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_USERS);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->required()
                    ->disabledOn('edit'),
                Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
                Forms\Components\Textarea::make('bio')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('avatar_url')->url()->maxLength(2048),
                Forms\Components\TextInput::make('cover_image_url')->url()->maxLength(2048),
                Forms\Components\DatePicker::make('birth_date'),
                Forms\Components\TextInput::make('gender')->maxLength(32),
                Forms\Components\TextInput::make('level')->numeric()->minValue(1)->required(),
                Forms\Components\Toggle::make('is_vip'),
                Forms\Components\Toggle::make('is_private'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')->label('User')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('display_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('level')->sortable(),
                Tables\Columns\IconColumn::make('is_vip')->boolean(),
                Tables\Columns\IconColumn::make('is_private')->boolean(),
                Tables\Columns\TextColumn::make('followers_count')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
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
            'index' => Pages\ListProfiles::route('/'),
            'create' => Pages\CreateProfile::route('/create'),
            'edit' => Pages\EditProfile::route('/{record}/edit'),
        ];
    }
}
