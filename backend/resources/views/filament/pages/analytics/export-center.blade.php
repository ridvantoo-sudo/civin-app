<x-filament-panels::page>
    <form wire:submit="export" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-start">
            <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                Download export
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
