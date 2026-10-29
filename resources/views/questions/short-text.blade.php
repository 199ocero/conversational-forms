@php
    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $hasError = $errors->has($statePath);
    $description = $getDescription();

    $attributes = $getExtraInputAttributeBag()
        ->merge([
            'id' => $id,
            'type' => $getInputType(),
            'placeholder' => $getPlaceholder(),
            'autocomplete' => $getAutocomplete(),
            'inputmode' => $getInputMode(),
            'maxlength' => $getMaxLength(),
            'minlength' => $getMinLength(),
            'autofocus' => $isAutofocused(),
            'disabled' => $isDisabled(),
            'aria-describedby' => collect([filled($description) ? "{$id}-description" : null, $hasError ? "{$id}-error" : null])->filter()->implode(' ') ?: null,
            'aria-invalid' => $hasError ? 'true' : null,
            'aria-required' => $isRequired() ? 'true' : null,
            $applyStateBindingModifiers('wire:model') => $statePath,
        ], escape: false)
        ->class(['cf-input']);
@endphp

<x-conversational-forms::question :field="$field">
    <input {{ $attributes }} />
</x-conversational-forms::question>
