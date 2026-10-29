@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $hasError = $errors->has($statePath);
    $description = $getDescription();
    $choices = $getChoices();
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('choice-question', ConversationalFormsServiceProvider::getAssetPackageName()) }}"
        x-data="choiceQuestionComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            choices: @js(array_map(fn (array $choice): array => ['value' => $choice['value'], 'key' => $choice['key']], $choices)),
            shouldAutoAdvance: @js($shouldAutoAdvance()),
        })"
        x-on:keydown.window="handleShortcut($event)"
        x-on:keydown="handleChoiceKeydown($event)"
        role="radiogroup"
        aria-labelledby="{{ $id }}-label"
        @if (filled($description) || $hasError)
            aria-describedby="{{ collect([filled($description) ? "{$id}-description" : null, $hasError ? "{$id}-error" : null])->filter()->implode(' ') }}"
        @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($isRequired()) aria-required="true" @endif
        class="cf-choices cf-choices-inline"
    >
        @foreach ($choices as $choice)
            @php($value = \Illuminate\Support\Js::from($choice['value']))

            <button
                type="button"
                role="radio"
                x-bind:aria-checked="isSelected({{ $value }}) ? 'true' : 'false'"
                x-bind:tabindex="getTabIndex({{ $value }}, {{ $loop->index }})"
                x-bind:class="{ 'cf-selected': isSelected({{ $value }}) }"
                x-on:click="choose({{ $value }}, { isExplicit: true })"
                aria-keyshortcuts="{{ strtoupper($choice['key']) }}"
                @disabled($isDisabled())
                data-cf-choice
                class="cf-choice"
            >
                <kbd class="cf-choice-key" aria-hidden="true">{{ strtoupper($choice['key']) }}</kbd>
                <span class="cf-choice-label">{{ $choice['label'] }}</span>
            </button>
        @endforeach
    </div>
</x-conversational-forms::question>
