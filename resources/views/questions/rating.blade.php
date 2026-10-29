@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $hasError = $errors->has($statePath);
    $description = $getDescription();
    $choices = $getChoices();

    $icon = match ($getShape()) {
        'heart' => '<path d="m9.653 16.915-.005-.003-.019-.01a20.759 20.759 0 0 1-1.162-.682 22.045 22.045 0 0 1-2.582-1.9C4.045 12.733 2 10.352 2 7.5a4.5 4.5 0 0 1 8-2.828A4.5 4.5 0 0 1 18 7.5c0 2.852-2.044 5.233-3.885 6.82a22.049 22.049 0 0 1-3.744 2.582l-.019.01-.005.003h-.002a.739.739 0 0 1-.69.001l-.002-.001Z" />',
        'thumb' => '<path d="M1 8.25a1.25 1.25 0 1 1 2.5 0v7.5a1.25 1.25 0 1 1-2.5 0v-7.5ZM11 3V1.7c0-.268.14-.526.395-.607A2 2 0 0 1 14 3c0 .995-.182 1.948-.514 2.826-.204.54.166 1.174.744 1.174h2.52c1.243 0 2.261 1.01 2.146 2.247a23.864 23.864 0 0 1-1.341 5.974C17.153 16.323 16.072 17 14.9 17h-3.192a3 3 0 0 1-1.341-.317l-2.734-1.366A3 3 0 0 0 6.292 15H5V8h.963c.685 0 1.258-.483 1.612-1.068a4.011 4.011 0 0 1 2.166-1.73c.432-.143.853-.386 1.011-.814.16-.432.248-.9.248-1.388Z" />',
        default => '<path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401Z" clip-rule="evenodd" />',
    };
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
        class="cf-rating"
    >
        @foreach ($choices as $choice)
            <button
                type="button"
                role="radio"
                x-bind:aria-checked="isSelected({{ $choice['value'] }}) ? 'true' : 'false'"
                x-bind:tabindex="getTabIndex({{ $choice['value'] }}, {{ $loop->index }})"
                x-bind:class="{ 'cf-filled': Number(state) >= {{ $choice['value'] }} }"
                x-on:click="choose({{ $choice['value'] }}, { isExplicit: true })"
                aria-label="{{ $choice['label'] }}"
                @disabled($isDisabled())
                data-cf-choice
                class="cf-rating-option"
            >
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">{!! $icon !!}</svg>
                <span class="cf-rating-number" aria-hidden="true">{{ $choice['value'] }}</span>
            </button>
        @endforeach
    </div>
</x-conversational-forms::question>
