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
    $allowsOther = $allowsOther();
    $otherKey = $allowsOther ? $getOtherKey() : null;
    $minSelections = $getMinSelections();
    $maxSelections = $getMaxSelections();

    $selectionHint = match (true) {
        ! $isMultiple => null,
        filled($minSelections) && filled($maxSelections) => __('conversational-forms::conversational-forms.questions.choose_between', ['min' => $minSelections, 'max' => $maxSelections]),
        filled($minSelections) => __('conversational-forms::conversational-forms.questions.choose_at_least', ['min' => $minSelections]),
        filled($maxSelections) => __('conversational-forms::conversational-forms.questions.choose_up_to', ['max' => $maxSelections]),
        default => __('conversational-forms::conversational-forms.questions.choose_as_many'),
    };
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    @if ($selectionHint)
        <p id="{{ $id }}-hint" class="cf-hint">{{ $selectionHint }}</p>
    @endif

    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('choice-question', ConversationalFormsServiceProvider::getAssetPackageName()) }}"
        x-data="choiceQuestionComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            choices: @js(array_map(fn (array $choice): array => ['value' => $choice['value'], 'key' => $choice['key']], $choices)),
            isMultiple: @js($isMultiple),
            maxSelections: @js($maxSelections),
            otherKey: @js($otherKey),
            shouldAutoAdvance: @js($shouldAutoAdvance()),
        })"
        x-on:keydown.window="handleShortcut($event)"
        x-on:keydown="handleChoiceKeydown($event)"
        role="{{ $isMultiple ? 'group' : 'radiogroup' }}"
        aria-labelledby="{{ $id }}-label"
        @if (filled($description) || $selectionHint || $hasError)
            aria-describedby="{{ collect([filled($description) ? "{$id}-description" : null, $selectionHint ? "{$id}-hint" : null, $hasError ? "{$id}-error" : null])->filter()->implode(' ') }}"
        @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ((! $isMultiple) && $isRequired()) aria-required="true" @endif
        class="cf-choices"
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
                class="cf-choice"
            >
                @if (filled($choice['key']))
                    <kbd class="cf-choice-key" aria-hidden="true">{{ strtoupper($choice['key']) }}</kbd>
                @endif

                <span class="cf-choice-label">{{ $choice['label'] }}</span>

                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="cf-choice-check"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
            </button>
        @endforeach

        @if ($allowsOther)
            <div x-bind:class="{ 'cf-selected': isOtherSelected }" class="cf-choice cf-choice-other">
                <button
                    type="button"
                    role="{{ $isMultiple ? 'checkbox' : 'radio' }}"
                    x-bind:aria-checked="isOtherSelected ? 'true' : 'false'"
                    x-bind:tabindex="getTabIndex(null, {{ count($choices) }}, true)"
                    x-on:click="chooseOther()"
                    @if (filled($otherKey)) aria-keyshortcuts="{{ strtoupper($otherKey) }}" @endif
                    @disabled($isDisabled())
                    data-cf-choice
                    class="cf-choice-other-toggle"
                >
                    @if (filled($otherKey))
                        <kbd class="cf-choice-key" aria-hidden="true">{{ strtoupper($otherKey) }}</kbd>
                    @endif

                    <span class="cf-choice-label">{{ __('conversational-forms::conversational-forms.questions.other') }}</span>

                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="cf-choice-check"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                </button>

                <input
                    type="text"
                    x-show="isOtherSelected"
                    x-ref="otherInput"
                    x-model="otherText"
                    aria-label="{{ __('conversational-forms::conversational-forms.questions.other') }}"
                    placeholder="{{ __('conversational-forms::conversational-forms.questions.other_placeholder') }}"
                    class="cf-choice-other-input"
                />
            </div>
        @endif
    </div>
</x-conversational-forms::question>
