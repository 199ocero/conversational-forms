@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $package = ConversationalFormsServiceProvider::getAssetPackageName();
    $text = fn (string $key): string => __("conversational-forms::conversational-forms.builder.editor.{$key}");

    $translations = [
        ...__('conversational-forms::conversational-forms.builder.editor'),
        'ending' => __('conversational-forms::conversational-forms.builder.form.ending'),
        'contrast' => __('conversational-forms::conversational-forms.themes.contrast'),
        ...collect(['yes', 'no', 'accept', 'decline', 'nps_start', 'nps_end', 'day', 'month', 'year', 'hour', 'minute', 'meridiem', 'placeholder', 'email_placeholder', 'phone_placeholder', 'number_placeholder', 'choose_as_many', 'choose_between', 'choose_at_least', 'choose_up_to'])
            ->mapWithKeys(fn (string $key): array => [$key => __("conversational-forms::conversational-forms.questions.{$key}")])
            ->all(),
    ];
@endphp

<x-filament-panels::page class="cf-editor-page">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('builder-editor', $package) }}"
        x-load-css="[@js(FilamentAsset::getStyleHref('conversational-forms', $package))]"
        x-data="builderEditorComponent({
            configuration: @js($this->getEditorConfiguration()),
            translations: @js($translations),
        })"
        x-on:keydown="handleKeydown($event)"
        x-on:keydown.escape="picker = null; confirmingDelete = null; closePanel()"
        x-bind:data-cf-pane="pane"
        x-bind:data-cf-view="view"
        x-cloak
        wire:ignore.self
        class="cf-editor"
    >
        {{-- Below the large breakpoint, the outline and the preview become tabs. --}}
        <div class="cf-editor-tabs" role="tablist" wire:ignore>
            @foreach (['outline', 'preview'] as $pane)
                <button
                    type="button"
                    role="tab"
                    x-on:click="pane = @js($pane)"
                    x-bind:aria-selected="pane === @js($pane) ? 'true' : 'false'"
                    class="cf-editor-tab"
                >
                    {{ $text($pane) }}
                </button>
            @endforeach
        </div>

        <aside
            data-cf-editor-outline
            aria-label="{{ $text('outline') }}"
            class="cf-editor-pane cf-editor-outline"
            wire:ignore
        >
            @include('conversational-forms::builder.editor.outline')
        </aside>

        <section
            aria-label="{{ $text('preview') }}"
            class="cf-editor-pane cf-editor-preview"
            wire:ignore
        >
            @include('conversational-forms::builder.editor.preview')
        </section>

        {{-- The selected item's settings, opened on demand; in the logic view, every step's logic. --}}
        <aside
            x-bind:aria-label="view === 'logic' ? @js($text('logic_heading')) : @js($text('inspector'))"
            x-bind:class="{ 'cf-open': isPanelOpen }"
            x-on:click.outside="closePanelFromOutside($event)"
            x-bind:inert="view !== 'logic' && ! isPanelOpen"
            class="cf-editor-panel"
        >
            <div class="cf-editor-panel-header" wire:ignore>
                <template x-if="view === 'logic'">
                    <div class="cf-editor-panel-intro">
                        <h2 class="cf-editor-panel-title">{{ $text('logic_heading') }}</h2>
                        <p class="cf-editor-panel-description">{{ $text('logic_description') }}</p>
                    </div>
                </template>

                <template x-if="view !== 'logic'">
                    <div class="cf-editor-panel-heading">
                        <span class="cf-editor-panel-icon" x-html="selectionIcon" aria-hidden="true"></span>
                        <h2 class="cf-editor-panel-title" x-text="selectionTitle"></h2>

                        <button type="button" x-on:click="closePanel()" title="{{ $text('close') }}" class="cf-editor-icon-button">
                            <x-filament::icon icon="heroicon-m-x-mark" />
                            <span class="cf-sr-only">{{ $text('close') }}</span>
                        </button>
                    </div>
                </template>
            </div>

            <div class="cf-editor-panel-body">
                <p x-show="view === 'logic' && isEmpty" class="cf-editor-empty">{{ $text('logic_empty') }}</p>

                {{ $this->form }}
            </div>
        </aside>
    </div>
</x-filament-panels::page>
