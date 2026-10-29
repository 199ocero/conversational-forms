@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $hasError = $errors->has($statePath) || $errors->has("{$statePath}.*");
    $description = $getDescription();
    $choices = $getChoices();
    $isMultiple = $isMultiple();
    $hasLabels = $hasLabels();
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    @if ($isMultiple)
        <p id="{{ $id }}-hint" class="cf-hint">{{ __('conversational-forms::conversational-forms.questions.choose_as_many') }}</p>
    @endif

    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('choice-question', ConversationalFormsServiceProvider::getAssetPackageName()) }}"
        x-data="choiceQuestionComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            choices: @js(array_map(fn (array $choice): array => ['value' => $choice['value'], 'key' => $choice['key']], $choices)),
            isMultiple: @js($isMultiple),
            maxSelections: @js($getMaxSelections()),
            shouldAutoAdvance: @js($shouldAutoAdvance()),
        })"
        x-on:keydown.window="handleShortcut($event)"
        x-on:keydown="handleChoiceKeydown($event)"
        role="{{ $isMultiple ? 'group' : 'radiogroup' }}"
        aria-labelledby="{{ $id }}-label"
        @if (filled($description) || $hasError)
            aria-describedby="{{ collect([filled($description) ? "{$id}-description" : null, $hasError ? "{$id}-error" : null])->filter()->implode(' ') }}"
        @endif
        @if ($hasError) aria-invalid="true" @endif
        class="cf-pictures"
    >
        @foreach ($choices as $choice)
            @php($value = \Illuminate\Support\Js::from($choice['value']))

            <button
                type="button"
                role="{{ $isMultiple ? 'checkbox' : 'radio' }}"
                x-bind:aria-checked="isSelected({{ $value }}) ? 'true' : 'false'"
                x-bind:tabindex="getTabIndex({{ $value }}, {{ $loop->index }})"
                x-bind:class="{ 'cf-selected': isSelected({{ $value }}) }"
                x-on:click="choose({{ $value }}, { isExplicit: true })"
                @if (filled($choice['key'])) aria-keyshortcuts="{{ strtoupper($choice['key']) }}" @endif
                @disabled($isDisabled())
                data-cf-choice
                class="cf-choice cf-picture"
            >
                @if (filled($image = $getImage($choice['value'])))
                    <img src="{{ $image }}" alt="" loading="lazy" class="cf-picture-image" />
                @endif

                <span class="cf-picture-footer">
                    @if (filled($choice['key']))
                        <kbd class="cf-choice-key" aria-hidden="true">{{ strtoupper($choice['key']) }}</kbd>
                    @endif

                    <span @class(['cf-choice-label', 'cf-sr-only' => ! $hasLabels])>{{ $choice['label'] }}</span>
                </span>

                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="cf-choice-check"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
            </button>
        @endforeach
    </div>
</x-conversational-forms::question>
