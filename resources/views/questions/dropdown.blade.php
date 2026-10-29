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

<x-conversational-forms::question :field="$field">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('dropdown-question', ConversationalFormsServiceProvider::getAssetPackageName()) }}"
        x-data="dropdownQuestionComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            options: @js(array_map(fn (array $choice): array => ['value' => $choice['value'], 'label' => $choice['label']], $choices)),
            shouldAutoAdvance: @js($shouldAutoAdvance()),
        })"
        x-on:click.outside="close()"
        class="cf-dropdown"
    >
        <input
            id="{{ $id }}"
            type="text"
            role="combobox"
            autocomplete="off"
            aria-autocomplete="list"
            aria-controls="{{ $id }}-listbox"
            x-bind:aria-expanded="isOpen ? 'true' : 'false'"
            x-bind:aria-activedescendant="isOpen && activeIndex !== null ? `{{ $id }}-option-${activeIndex}` : null"
            @if (filled($description) || $hasError)
                aria-describedby="{{ collect([filled($description) ? "{$id}-description" : null, $hasError ? "{$id}-error" : null])->filter()->implode(' ') }}"
            @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($isRequired()) aria-required="true" @endif
            @disabled($isDisabled())
            placeholder="{{ __('conversational-forms::conversational-forms.questions.dropdown_placeholder') }}"
            x-model="query"
            x-on:focus="open()"
            x-on:click="open()"
            x-on:input="open(); activeIndex = 0"
            x-on:keydown="handleKeydown($event)"
            class="cf-input cf-dropdown-input"
        />

        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="cf-dropdown-chevron"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>

        <ul
            id="{{ $id }}-listbox"
            role="listbox"
            aria-labelledby="{{ $id }}-label"
            x-show="isOpen"
            x-cloak
            x-ref="listbox"
            class="cf-dropdown-listbox"
        >
            <template x-for="(option, index) in filteredOptions" x-bind:key="index">
                <li
                    role="option"
                    x-bind:id="`{{ $id }}-option-${index}`"
                    x-bind:aria-selected="isSelected(option.value) ? 'true' : 'false'"
                    x-bind:class="{ 'cf-active-option': activeIndex === index, 'cf-selected': isSelected(option.value) }"
                    x-on:mousedown.prevent="choose(option)"
                    x-on:mousemove="activeIndex = index"
                    x-text="option.label"
                    class="cf-dropdown-option"
                ></li>
            </template>

            <li x-show="filteredOptions.length === 0" role="presentation" class="cf-dropdown-empty">
                {{ __('conversational-forms::conversational-forms.questions.dropdown_empty') }}
            </li>
        </ul>
    </div>
</x-conversational-forms::question>
