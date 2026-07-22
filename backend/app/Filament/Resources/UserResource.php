<?php

namespace App\Filament\Resources;

use App\Features\Admin\Actions\BanUser;
use App\Features\Admin\Actions\UnbanUser;
use App\Features\Admin\Support\AdminPermission;
use App\Features\Admin\Support\AdminRole;
use App\Features\Users\Models\User;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\UserResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Community';

    protected static ?int $navigationSort = 1;

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
                Forms\Components\Section::make('Account')
                    ->schema([
                        Forms\Components\TextInput::make('username')->required()->maxLength(255)->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('email')->email()->maxLength(255)->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create'),
                        Forms\Components\Select::make('status')
                            ->options([
                                User::STATUS_ACTIVE => 'Active',
                                User::STATUS_BANNED => 'Banned',
                                User::STATUS_DELETED => 'Deleted',
                            ])
                            ->required(),
                        Forms\Components\Toggle::make('is_admin')->label('Legacy admin flag'),
                        Forms\Components\Toggle::make('is_guest')->disabled(),
                        Forms\Components\Select::make('roles')
                            ->label('Admin roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload()
                            ->visible(fn (): bool => auth()->user()?->hasRole(AdminRole::SUPER_ADMIN) ?? false),
                    ])->columns(2),
                Forms\Components\Section::make('Moderation')
                    ->schema([
                        Forms\Components\DateTimePicker::make('banned_at')->disabled(),
                        Forms\Components\Textarea::make('ban_reason')->rows(2)->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Security')
                    ->schema([
                        Forms\Components\Placeholder::make('two_factor_status')
                            ->label('Two-factor')
                            ->content(fn (?User $record): string => $record?->hasTwoFactorEnabled()
                                ? 'Enabled'
                                : 'Ready (not enabled)'),
                        Forms\Components\DateTimePicker::make('two_factor_confirmed_at')->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        User::STATUS_ACTIVE => 'success',
                        User::STATUS_BANNED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_admin')->boolean()->label('Admin'),
                Tables\Columns\TextColumn::make('roles.name')->badge()->label('Roles'),
                Tables\Columns\TextColumn::make('last_login_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        User::STATUS_ACTIVE => 'Active',
                        User::STATUS_BANNED => 'Banned',
                        User::STATUS_DELETED => 'Deleted',
                    ]),
                Tables\Filters\TernaryFilter::make('is_admin'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('ban')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Ban reason')->required()->maxLength(500),
                    ])
                    ->visible(fn (User $record): bool => ! $record->isBanned())
                    ->action(function (User $record, array $data): void {
                        app(BanUser::class)->execute(auth()->user(), $record, $data['reason'] ?? null);
                        Notification::make()->title('User banned')->success()->send();
                    }),
                Tables\Actions\Action::make('unban')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->isBanned())
                    ->action(function (User $record): void {
                        app(UnbanUser::class)->execute(auth()->user(), $record);
                        Notification::make()->title('User unbanned')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
