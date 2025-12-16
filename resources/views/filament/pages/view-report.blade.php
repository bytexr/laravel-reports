<x-filament-panels::page>
    {{ $this->table }}

    <x-filament::modal id="history-modal" width="2xl">
        <x-slot name="heading">
            @if($this->selectedReport)
                Version History: {{ $this->selectedReport->name }}
            @else
                Version History
            @endif
        </x-slot>

        <x-slot name="description">
            View and restore previous versions of this report configuration.
        </x-slot>

        <div class="py-4">
            {!! $this->getHistoryContent() !!}
        </div>

        <x-slot name="footerActions">
            <x-filament::button color="gray" wire:click="closeHistoryModal">
                Close
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
