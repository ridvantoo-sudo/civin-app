<?php

namespace App\Filament\Resources;

use App\Features\Admin\Actions\BanUser;
use App\Features\Admin\Actions\DisableUserTwoFactor;
use App\Features\Admin\Actions\ForceDeleteUser;
use App\Features\Admin\Actions\ForceLogoutUser;
use App\Features\Admin\Actions\ResetUserPassword;
use App\Features\Admin\Actions\RestoreUser;
use App\Features\Admin\Actions\SoftDeleteUser;
use App\Features\Admin\Actions\UnbanUser;
use App\Features\Admin\Actions\VerifyUserEmail;
use App\Features\Admin\Support\AdminPermission;
use App\Features\Admin\Support\AdminRole;
use App\Features\Users\Models\User;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Forms\Components\CountryLocationFields;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Community';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'username';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_USERS);
    }

    public static function canView($record): bool
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

    public static function canRestore($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_USERS);
    }

    public static function canForceDelete($record): bool
    {
        return auth()->user()?->hasRole(AdminRole::SUPER_ADMIN) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Account')
                    ->schema([
                        Forms\Components\TextInput::make('username')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? 'Leave blank to keep the current password.'
                                : null),
                        Forms\Components\Select::make('status')
                            ->options([
                                User::STATUS_ACTIVE => 'Active',
                                User::STATUS_BANNED => 'Banned',
                                User::STATUS_DELETED => 'Deleted',
                            ])
                            ->required(),
                        Forms\Components\Toggle::make('is_admin')->label('Legacy admin flag'),
                        Forms\Components\Toggle::make('is_guest')->disabled(),
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email verified at'),
                        Forms\Components\TextInput::make('firebase_uid')
                            ->label('Firebase UID')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('roles')
                            ->label('Admin roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload()
                            ->visible(fn (): bool => auth()->user()?->hasRole(AdminRole::SUPER_ADMIN) ?? false),
                    ])->columns(2),
                Forms\Components\Section::make('Profile')
                    ->relationship('profile')
                    ->schema([
                        Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
                        ...CountryLocationFields::make(),
                        Forms\Components\Textarea::make('bio')->rows(3)->columnSpanFull(),
                        Forms\Components\TextInput::make('avatar_url')->url()->maxLength(2048),
                        Forms\Components\TextInput::make('cover_image_url')->url()->maxLength(2048),
                        Forms\Components\DatePicker::make('birth_date'),
                        Forms\Components\TextInput::make('gender')->maxLength(32),
                        Forms\Components\TextInput::make('level')->numeric()->minValue(1)->default(1)->required(),
                        Forms\Components\Toggle::make('is_vip'),
                        Forms\Components\Toggle::make('is_svip'),
                        Forms\Components\Toggle::make('is_private'),
                    ])
                    ->columns(2)
                    ->visibleOn(['edit', 'view']),
                Forms\Components\Section::make('Wallet')
                    ->schema([
                        Forms\Components\Placeholder::make('coins_balance')
                            ->label('Coins')
                            ->content(fn (?User $record): string => (string) ($record?->wallet?->coins_balance ?? 0)),
                        Forms\Components\Placeholder::make('diamonds_balance')
                            ->label('Diamonds')
                            ->content(fn (?User $record): string => (string) ($record?->wallet?->diamonds_balance ?? 0)),
                    ])
                    ->columns(2)
                    ->visibleOn('edit'),
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
                        Forms\Components\Placeholder::make('active_tokens')
                            ->label('Active API tokens')
                            ->content(fn (?User $record): string => (string) ($record?->tokens()->count() ?? 0)),
                        Forms\Components\DateTimePicker::make('last_login_at')->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('profile.display_name')
                    ->label('Display name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        User::STATUS_ACTIVE => 'success',
                        User::STATUS_BANNED => 'danger',
                        User::STATUS_DELETED => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_admin')->boolean()->label('Admin'),
                Tables\Columns\IconColumn::make('is_guest')->boolean()->label('Guest')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('roles.name')->badge()->label('Roles'),
                Tables\Columns\TextColumn::make('wallet.coins_balance')
                    ->label('Coins')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('wallet.diamonds_balance')
                    ->label('Diamonds')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->email_verified_at !== null)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_login_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        User::STATUS_ACTIVE => 'Active',
                        User::STATUS_BANNED => 'Banned',
                        User::STATUS_DELETED => 'Deleted',
                    ]),
                Tables\Filters\TernaryFilter::make('is_admin'),
                Tables\Filters\TernaryFilter::make('is_guest'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('ban')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Ban reason')->required()->maxLength(500),
                    ])
                    ->visible(fn (User $record): bool => ! $record->trashed() && ! $record->isBanned())
                    ->action(function (User $record, array $data): void {
                        app(BanUser::class)->execute(auth()->user(), $record, $data['reason'] ?? null);
                        Notification::make()->title('User banned')->success()->send();
                    }),
                Tables\Actions\Action::make('unban')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! $record->trashed() && $record->isBanned())
                    ->action(function (User $record): void {
                        app(UnbanUser::class)->execute(auth()->user(), $record);
                        Notification::make()->title('User unbanned')->success()->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('forceLogout')
                        ->label('Force logout')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Revokes all API tokens for this user.')
                        ->visible(fn (User $record): bool => ! $record->trashed())
                        ->action(function (User $record): void {
                            app(ForceLogoutUser::class)->execute(auth()->user(), $record);
                            Notification::make()->title('User logged out')->success()->send();
                        }),
                    Tables\Actions\Action::make('resetPassword')
                        ->label('Reset password')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->form([
                            Forms\Components\TextInput::make('password')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->confirmed(),
                            Forms\Components\TextInput::make('password_confirmation')
                                ->password()
                                ->required()
                                ->label('Confirm password'),
                        ])
                        ->visible(fn (User $record): bool => ! $record->trashed())
                        ->action(function (User $record, array $data): void {
                            app(ResetUserPassword::class)->execute(auth()->user(), $record, $data['password']);
                            Notification::make()->title('Password reset')->success()->send();
                        }),
                    Tables\Actions\Action::make('verifyEmail')
                        ->label('Verify email')
                        ->icon('heroicon-o-envelope-open')
                        ->visible(fn (User $record): bool => ! $record->trashed() && $record->email_verified_at === null)
                        ->action(function (User $record): void {
                            app(VerifyUserEmail::class)->execute(auth()->user(), $record);
                            Notification::make()->title('Email verified')->success()->send();
                        }),
                    Tables\Actions\Action::make('disableTwoFactor')
                        ->label('Disable 2FA')
                        ->icon('heroicon-o-shield-exclamation')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool => ! $record->trashed() && $record->hasTwoFactorEnabled())
                        ->action(function (User $record): void {
                            app(DisableUserTwoFactor::class)->execute(auth()->user(), $record);
                            Notification::make()->title('Two-factor disabled')->success()->send();
                        }),
                    Tables\Actions\RestoreAction::make()
                        ->action(function (User $record): void {
                            app(RestoreUser::class)->execute(auth()->user(), $record);
                            Notification::make()->title('User restored')->success()->send();
                        }),
                    Tables\Actions\DeleteAction::make()
                        ->action(function (User $record): void {
                            app(SoftDeleteUser::class)->execute(auth()->user(), $record);
                            Notification::make()->title('User deleted')->success()->send();
                        }),
                    Tables\Actions\ForceDeleteAction::make()
                        ->action(function (User $record): void {
                            app(ForceDeleteUser::class)->execute(auth()->user(), $record);
                            Notification::make()->title('User permanently deleted')->success()->send();
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('banSelected')
                        ->label('Ban selected')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')->label('Ban reason')->required()->maxLength(500),
                        ])
                        ->action(function ($records, array $data): void {
                            $admin = auth()->user();
                            foreach ($records as $record) {
                                if ($record->trashed() || $record->isBanned() || $admin->is($record)) {
                                    continue;
                                }
                                app(BanUser::class)->execute($admin, $record, $data['reason'] ?? null);
                            }
                            Notification::make()->title('Selected users banned')->success()->send();
                        }),
                    Tables\Actions\BulkAction::make('unbanSelected')
                        ->label('Unban selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $admin = auth()->user();
                            foreach ($records as $record) {
                                if ($record->trashed() || ! $record->isBanned()) {
                                    continue;
                                }
                                app(UnbanUser::class)->execute($admin, $record);
                            }
                            Notification::make()->title('Selected users unbanned')->success()->send();
                        }),
                    Tables\Actions\BulkAction::make('forceLogoutSelected')
                        ->label('Force logout selected')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $admin = auth()->user();
                            foreach ($records as $record) {
                                if ($record->trashed() || $admin->is($record)) {
                                    continue;
                                }
                                app(ForceLogoutUser::class)->execute($admin, $record);
                            }
                            Notification::make()->title('Selected users logged out')->success()->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records): void {
                            $admin = auth()->user();
                            foreach ($records as $record) {
                                if ($admin->is($record)) {
                                    continue;
                                }
                                app(SoftDeleteUser::class)->execute($admin, $record);
                            }
                            Notification::make()->title('Selected users deleted')->success()->send();
                        }),
                    Tables\Actions\RestoreBulkAction::make()
                        ->action(function ($records): void {
                            $admin = auth()->user();
                            foreach ($records as $record) {
                                if (! $record->trashed()) {
                                    continue;
                                }
                                app(RestoreUser::class)->execute($admin, $record);
                            }
                            Notification::make()->title('Selected users restored')->success()->send();
                        }),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->hasRole(AdminRole::SUPER_ADMIN) ?? false)
                        ->action(function ($records): void {
                            $admin = auth()->user();
                            foreach ($records as $record) {
                                if ($admin->is($record) || $record->hasRole(AdminRole::SUPER_ADMIN)) {
                                    continue;
                                }
                                app(ForceDeleteUser::class)->execute($admin, $record);
                            }
                            Notification::make()->title('Selected users permanently deleted')->success()->send();
                        }),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['roles', 'profile', 'wallet']));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DevicesRelationManager::class,
            RelationManagers\WalletTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
