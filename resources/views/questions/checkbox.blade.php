@php
    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $hasError = $errors->has($statePath);
    $description = $getDescription();
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    <div x-data="{ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} }" class="cf-choices">
        <button
            type="button"
            role="checkbox"
            x-bind:aria-checked="state ? 'true' : 'false'"
            x-bind:class="{ 'cf-selected': state }"
            x-on:click="state = ! state"
            {{-- Space ticks the box; Enter moves on, like in multiple choice questions. --}}
            x-on:keydown.enter.prevent="next()"
            aria-labelledby="{{ $id }}-label {{ $id }}-checkbox-label"
            @if (filled($description) || $hasError)
                aria-describedby="{{ collect([filled($description) ? "{$id}-description" : null, $hasError ? "{$id}-error" : null])->filter()->implode(' ') }}"
            @endif
            @if ($hasError) aria-invalid="true" @endif
            @disabled($isDisabled())
            data-cf-choice
            class="cf-choice cf-checkbox"
        >
            <span class="cf-checkbox-box" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="currentColor" class="cf-checkbox-check"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
            </span>

            <span id="{{ $id }}-checkbox-label" class="cf-choice-label">{{ $getCheckboxLabel() }}</span>
        </button>
    </div>
</x-conversational-forms::question>
