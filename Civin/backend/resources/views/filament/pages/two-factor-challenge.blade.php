<x-filament-panels::page>
    <form wire:submit="verify" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit">
            Verify
        </x-filament::button>
    </form>
</x-filament-panels::page>
