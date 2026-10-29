@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $hasError = $errors->has($statePath);
    $description = $getDescription();
    $choices = $getChoices();
    $startLabel = $getStartLabel();
    $endLabel = $getEndLabel();
    $hasLabels = filled($startLabel) || filled($endLabel);
    $total = count($choices);
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('choice-question', ConversationalFormsServiceProvider::getAssetPackageName()) }}"
        x-data="choiceQuestionComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            choices: @js(array_map(fn (array $choice): array => ['value' => $choice['value'], 'key' => $choice['key']], $choices)),
            shouldAutoAdvance: @js($shouldAutoAdvance()),
            orientation: 'horizontal',
        })"
        x-on:keydown.window="handleShortcut($event)"
        x-on:keydown="handleChoiceKeydown($event)"
        role="radiogroup"
        aria-labelledby="{{ $id }}-label"
        @if (filled($description) || $hasLabels || $hasError)
            aria-describedby="{{ collect([filled($description) ? "{$id}-description" : null, $hasLabels ? "{$id}-scale-labels" : null, $hasError ? "{$id}-error" : null])->filter()->implode(' ') }}"
        @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($isRequired()) aria-required="true" @endif
        class="cf-scale"
        style="--cf-scale-steps: {{ $total }}"
    >
        @foreach ($choices as $choice)
            <button
                type="button"
                role="radio"
                x-bind:aria-checked="isSelected({{ $choice['value'] }}) ? 'true' : 'false'"
                x-bind:tabindex="getTabIndex({{ $choice['value'] }}, {{ $loop->index }})"
                x-bind:class="{ 'cf-selected': isSelected({{ $choice['value'] }}) }"
                x-on:click="choose({{ $choice['value'] }}, { isExplicit: true })"
                aria-label="{{ $choice['label'] }} / {{ $choices[$total - 1]['label'] }}"
                @disabled($isDisabled())
                data-cf-choice
                class="cf-scale-option"
            >{{ $choice['label'] }}</button>
        @endforeach
    </div>

    @if ($hasLabels)
        <div id="{{ $id }}-scale-labels" class="cf-scale-labels">
            <span>@if (filled($startLabel)) {{ $choices[0]['label'] }}: {{ $startLabel }} @endif</span>
            <span>@if (filled($endLabel)) {{ $choices[$total - 1]['label'] }}: {{ $endLabel }} @endif</span>
        </div>
    @endif
</x-conversational-forms::question>
