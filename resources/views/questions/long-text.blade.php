@php
    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $hasError = $errors->has($statePath);
    $description = $getDescription();
    $shouldSubmitOnEnter = $shouldSubmitOnEnter();

    $attributes = $getExtraInputAttributeBag()
        ->merge([
            'id' => $id,
            'rows' => $getRows(),
            'placeholder' => $getPlaceholder(),
            'maxlength' => $getMaxLength(),
            'minlength' => $getMinLength(),
            'autofocus' => $isAutofocused(),
            'disabled' => $isDisabled(),
            'aria-describedby' => collect([filled($description) ? "{$id}-description" : null, "{$id}-hint", $hasError ? "{$id}-error" : null])->filter()->implode(' '),
            'aria-invalid' => $hasError ? 'true' : null,
            'aria-required' => $isRequired() ? 'true' : null,
            $applyStateBindingModifiers('wire:model') => $statePath,
        ], escape: false)
        ->class(['cf-input', 'cf-textarea']);
@endphp

<x-conversational-forms::question :field="$field">
    <textarea
        @if ($shouldSubmitOnEnter)
            {{-- Enter moves on, Shift + Enter adds a line break. Ctrl / Cmd + Enter is handled by the conversation. --}}
            x-on:keydown.enter="if (! ($event.shiftKey || $event.altKey || $event.metaKey || $event.ctrlKey || $event.isComposing)) { $event.preventDefault(); next() }"
        @endif
        {{ $attributes }}
    ></textarea>

    <p id="{{ $id }}-hint" class="cf-hint">
        {!! $shouldSubmitOnEnter
            ? __('conversational-forms::conversational-forms.questions.long_text_hint')
            : __('conversational-forms::conversational-forms.questions.long_text_hint_without_submit') !!}
    </p>
</x-conversational-forms::question>
