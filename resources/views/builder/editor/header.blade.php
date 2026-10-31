@php
    use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;

    $navigation = ConversationalFormResource::getRecordSubNavigation($this);
@endphp

<header class="cf-editor-header">
    <div class="cf-editor-header-start">
        <a
            href="{{ ConversationalFormResource::getUrl('index') }}"
            wire:navigate
            class="cf-editor-back"
            title="{{ __('conversational-forms::conversational-forms.builder.editor.back') }}"
        >
            <x-filament::icon icon="heroicon-m-arrow-left" class="cf-editor-back-icon" />
            <span class="cf-sr-only">{{ __('conversational-forms::conversational-forms.builder.editor.back') }}</span>
        </a>

        <div class="cf-editor-title">
            <h1 class="cf-editor-heading">{{ $this->record->title }}</h1>

            <p class="cf-editor-subheading">
                <span>{{ $this->getSubheading() }}</span>

                <span
                    x-data
                    x-cloak
                    x-show="$store.cfEditor"
                    x-bind:class="{
                        'cf-editor-dirty': $store.cfEditor?.isDirty,
                        'cf-editor-save-problem': $store.cfEditor?.saveProblem && ! $store.cfEditor?.isSaving,
                    }"
                    class="cf-editor-save-state"
                    role="status"
                >
                    <span class="cf-editor-save-state-dot" aria-hidden="true"></span>
                    <span
                        x-text="
                            $store.cfEditor?.isSaving ? @js(__('conversational-forms::conversational-forms.builder.editor.saving'))
                            : $store.cfEditor?.saveProblem ? @js(__('conversational-forms::conversational-forms.builder.editor.save_failed'))
                            : $store.cfEditor?.isDirty ? @js(__('conversational-forms::conversational-forms.builder.editor.unsaved'))
                            : @js(__('conversational-forms::conversational-forms.builder.editor.saved'))
                        "
                        x-bind:title="$store.cfEditor?.saveProblem"
                    ></span>
                </span>
            </p>
        </div>
    </div>

    <nav class="cf-editor-nav" aria-label="{{ $this->record->title }}">
        @foreach ($navigation as $item)
            <a
                href="{{ $item->getUrl() }}"
                wire:navigate
                @if ($item->isActive()) aria-current="page" @endif
                @class(['cf-editor-nav-link', 'cf-active' => $item->isActive()])
            >
                {{ $item->getLabel() }}
            </a>
        @endforeach
    </nav>

    <div class="cf-editor-header-actions">
        <x-filament::actions :actions="$this->getCachedHeaderActions()" />
    </div>
</header>
