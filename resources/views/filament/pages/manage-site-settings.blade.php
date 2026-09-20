<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Editing settings for
        </x-slot>

        <div class="flex flex-wrap gap-2">
            @foreach ($this->branches() as $branch)
                <x-filament::button
                    type="button"
                    wire:click="selectBranch({{ $branch->id }})"
                    :color="$branchId === $branch->id ? 'primary' : 'gray'"
                    icon="heroicon-o-building-storefront"
                >
                    {{ $branch->name['en'] ?? '(untitled)' }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>

    <form wire:submit="save" class="mt-6">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Save Settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>