@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $hasError = $errors->has($statePath);
    $canBeTyped = $canBeTyped();
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('signature-question', ConversationalFormsServiceProvider::getAssetPackageName()) }}"
        x-data="signatureQuestionComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            translations: { font: @js('"Segoe Script", "Brush Script MT", "Snell Roundhand", cursive') },
        })"
        class="cf-signature"
    >
        <div class="cf-signature-pad" x-bind:class="{ 'cf-signature-typing': mode === 'type' }">
            <canvas
                role="img"
                aria-labelledby="{{ $id }}-label"
                x-bind:aria-description="hasDrawing ? @js(__('conversational-forms::conversational-forms.signature.signed')) : @js(__('conversational-forms::conversational-forms.signature.empty'))"
                x-on:pointerdown="start($event)"
                x-on:pointermove="draw($event)"
                x-on:pointerup="end()"
                x-on:pointercancel="end()"
                class="cf-signature-canvas"
            ></canvas>

            <span x-show="! hasDrawing && mode === 'draw'" class="cf-signature-placeholder" aria-hidden="true">{{ __('conversational-forms::conversational-forms.signature.draw_here') }}</span>
        </div>

        @if ($canBeTyped)
            <input
                type="text"
                x-show="mode === 'type'"
                x-cloak
                x-model="typedName"
                data-cf-signature-name
                autocomplete="name"
                aria-label="{{ __('conversational-forms::conversational-forms.signature.type_label') }}"
                placeholder="{{ __('conversational-forms::conversational-forms.signature.type_placeholder') }}"
                @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                class="cf-input"
            />
        @endif

        <div class="cf-signature-actions">
            <button type="button" x-on:click="clear()" x-bind:disabled="! hasDrawing" class="cf-link-button">
                {{ __('conversational-forms::conversational-forms.signature.clear') }}
            </button>

            @if ($canBeTyped)
                <button type="button" x-on:click="toggleMode()" class="cf-link-button">
                    <span x-show="mode === 'draw'">{{ __('conversational-forms::conversational-forms.signature.type_instead') }}</span>
                    <span x-show="mode === 'type'" x-cloak>{{ __('conversational-forms::conversational-forms.signature.draw_instead') }}</span>
                </button>
            @endif
        </div>
    </div>
</x-conversational-forms::question>
