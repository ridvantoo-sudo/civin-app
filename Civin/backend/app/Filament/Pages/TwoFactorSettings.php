<?php

namespace App\Filament\Pages;

use App\Support\Totp;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TwoFactorSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Two-factor authentication';

    protected static ?string $title = 'Two-factor authentication';

    protected static string $view = 'filament.pages.two-factor-settings';

    public ?array $data = [];

    public function mount(): void
    {
        if (! session()->has('admin_2fa_pending_secret') && ! auth()->user()?->hasTwoFactorEnabled()) {
            session(['admin_2fa_pending_secret' => Totp::generateSecret()]);
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        $secret = (string) session('admin_2fa_pending_secret', '');

        return $form
            ->schema([
                Placeholder::make('secret')
                    ->label('Setup key')
                    ->content($secret !== '' ? $secret : 'Two-factor authentication is already enabled.')
                    ->visible(fn (): bool => ! (bool) auth()->user()?->hasTwoFactorEnabled()),
                TextInput::make('code')
                    ->label('Code from your authenticator app')
                    ->length(6)
                    ->visible(fn (): bool => ! (bool) auth()->user()?->hasTwoFactorEnabled()),
            ])
            ->statePath('data');
    }

    public function enable(): void
    {
        $user = auth()->user();
        $secret = (string) session('admin_2fa_pending_secret', '');
        $code = (string) ($this->form->getState()['code'] ?? '');

        if ($user === null || $secret === '' || ! Totp::verify($secret, $code)) {
            Notification::make()->title('That code does not match the setup key.')->danger()->send();

            return;
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        session()->forget('admin_2fa_pending_secret');
        session(['admin_2fa_passed' => $user->getKey()]);

        Notification::make()->title('Two-factor authentication is on.')->success()->send();
        $this->redirect(static::getUrl());
    }

    public function disable(): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        session()->forget('admin_2fa_passed');
        session(['admin_2fa_pending_secret' => Totp::generateSecret()]);

        Notification::make()->title('Two-factor authentication is off.')->success()->send();
        $this->redirect(static::getUrl());
    }
}
