<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Features\Admin\Actions\BanUser;
use App\Features\Admin\Actions\DisableUserTwoFactor;
use App\Features\Admin\Actions\ForceDeleteUser;
use App\Features\Admin\Actions\ForceLogoutUser;
use App\Features\Admin\Actions\ResetUserPassword;
use App\Features\Admin\Actions\RestoreUser;
use App\Features\Admin\Actions\SoftDeleteUser;
use App\Features\Admin\Actions\UnbanUser;
use App\Features\Admin\Actions\VerifyUserEmail;
use App\Features\Admin\Support\AdminRole;
use App\Features\Users\Models\User;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('ban')
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
                    $this->refreshFormData(['status', 'banned_at', 'ban_reason']);
                }),
            Actions\Action::make('unban')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (User $record): bool => ! $record->trashed() && $record->isBanned())
                ->action(function (User $record): void {
                    app(UnbanUser::class)->execute(auth()->user(), $record);
                    Notification::make()->title('User unbanned')->success()->send();
                    $this->refreshFormData(['status', 'banned_at', 'ban_reason']);
                }),
            Actions\Action::make('forceLogout')
                ->label('Force logout')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (User $record): bool => ! $record->trashed())
                ->action(function (User $record): void {
                    app(ForceLogoutUser::class)->execute(auth()->user(), $record);
                    Notification::make()->title('User logged out')->success()->send();
                }),
            Actions\Action::make('resetPassword')
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
            Actions\Action::make('verifyEmail')
                ->label('Verify email')
                ->icon('heroicon-o-envelope-open')
                ->visible(fn (User $record): bool => ! $record->trashed() && $record->email_verified_at === null)
                ->action(function (User $record): void {
                    app(VerifyUserEmail::class)->execute(auth()->user(), $record);
                    Notification::make()->title('Email verified')->success()->send();
                    $this->refreshFormData(['email_verified_at']);
                }),
            Actions\Action::make('disableTwoFactor')
                ->label('Disable 2FA')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (User $record): bool => ! $record->trashed() && $record->hasTwoFactorEnabled())
                ->action(function (User $record): void {
                    app(DisableUserTwoFactor::class)->execute(auth()->user(), $record);
                    Notification::make()->title('Two-factor disabled')->success()->send();
                    $this->refreshFormData(['two_factor_confirmed_at']);
                }),
            Actions\RestoreAction::make()
                ->action(function (User $record): void {
                    app(RestoreUser::class)->execute(auth()->user(), $record);
                    Notification::make()->title('User restored')->success()->send();
                }),
            Actions\DeleteAction::make()
                ->action(function (User $record): void {
                    app(SoftDeleteUser::class)->execute(auth()->user(), $record);
                    Notification::make()->title('User deleted')->success()->send();
                }),
            Actions\ForceDeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->hasRole(AdminRole::SUPER_ADMIN) ?? false)
                ->action(function (User $record): void {
                    app(ForceDeleteUser::class)->execute(auth()->user(), $record);
                    Notification::make()->title('User permanently deleted')->success()->send();
                    $this->redirect(UserResource::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['roles'], $data['firebase_uid']);

        return $data;
    }
}
