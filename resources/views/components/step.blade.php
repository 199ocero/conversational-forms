@props([
    'step',
])

@php
    $name = $step->getName();
    $jsName = \Illuminate\Support\Js::from($name);
    $continueLabel = $step->getContinueLabel();
@endphp

<section
    data-cf-step="{{ $name }}"
    x-bind:class="{
        'cf-active': isActive({{ $jsName }}),
        'cf-before': isBefore({{ $jsName }}),
    }"
    x-bind:inert="! isActive({{ $jsName }})"
    x-bind:aria-hidden="isActive({{ $jsName }}) ? 'false' : 'true'"
    {{ $attributes->class(['cf-step']) }}
>
    <div class="cf-step-content">
        {{ $slot }}
    </div>

    <div class="cf-step-actions">
        <button
            type="button"
            x-on:click="next()"
            x-bind:disabled="isAdvancing"
            class="cf-button"
        >
            @if (filled($continueLabel))
                <span>{{ $continueLabel }}</span>
            @else
                <span x-text="isLastStep({{ $jsName }}) ? @js(__('conversational-forms::conversational-forms.navigation.submit')) : @js(__('conversational-forms::conversational-forms.navigation.ok'))">{{ __('conversational-forms::conversational-forms.navigation.ok') }}</span>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="cf-button-icon"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
            @endif
        </button>

        <span class="cf-hint cf-hint-enter" aria-hidden="true">{!! __('conversational-forms::conversational-forms.navigation.press_enter') !!}</span>
    </div>
</section>
