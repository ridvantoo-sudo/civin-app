<?php

namespace App\Filament\Pages;

use App\Support\Totp;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TwoFactorChallenge extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.two-factor-challenge';

    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();
        if ($user === null || ! $user->hasTwoFactorEnabled() || session('admin_2fa_passed') === $user->getKey()) {
            redirect()->to('/admin');

            return;
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label('Authentication code')
                    ->required()
                    ->length(6),
            ])
            ->statePath('data');
    }

    public function verify(): void
    {
        $code = (string) ($this->form->getState()['code'] ?? '');
        $user = auth()->user();

        if ($user === null || ! Totp::verify((string) $user->two_factor_secret, $code)) {
            Notification::make()->title('That code is not valid.')->danger()->send();

            return;
        }

        session(['admin_2fa_passed' => $user->getKey()]);
        $this->redirect('/admin');
    }
}
