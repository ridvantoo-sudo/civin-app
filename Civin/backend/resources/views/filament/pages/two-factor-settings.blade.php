<x-filament-panels::page>
    @if (auth()->user()?->hasTwoFactorEnabled())
        <p>Authenticator codes are required the next time you sign in.</p>
        <x-filament::button color="danger" wire:click="disable" class="mt-4">
            Turn off
        </x-filament::button>
    @else
        <form wire:submit="enable" class="space-y-4">
            {{ $this->form }}
            <x-filament::button type="submit">
                Turn on
            </x-filament::button>
        </form>
    @endif
</x-filament-panels::page>
