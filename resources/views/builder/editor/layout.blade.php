{{-- The panel's page shell (assets, theme, notifications and modals), without its sidebar and topbar: the editor fills the window. --}}
<x-filament-panels::layout.base :livewire="$livewire" class="cf-editor-body">
    {{ $slot }}
</x-filament-panels::layout.base>
