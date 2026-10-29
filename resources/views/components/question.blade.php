@props([
    'field',
    'labelTarget' => true,
    'showsNestedErrors' => true,
])

@php
    // Read the Livewire error bag directly: partially rendered steps do not
    // receive the `$errors` variable that full renders share with views.
    $errors = $field->getLivewire()->getErrorBag();
    $id = $field->getId();
    $statePath = $field->getStatePath();
    $label = $field->getPipedLabel();
    $description = $field->getPipedDescription();
    $media = $field->getMedia();
    $number = $field->getQuestionNumber();
    $isRequired = $field->isMarkedAsRequired() && $field->isRequired();
    $errorMessage = $errors->first($statePath) ?: ($showsNestedErrors ? $errors->first("{$statePath}.*") : null);
    $labelTag = $labelTarget ? 'label' : 'div';
@endphp

<div @class(['cf-question', 'cf-question-with-media' => filled($media)]) data-cf-question="{{ $statePath }}">
    <div class="cf-question-header">
        @if ($number)
            <span class="cf-question-number" aria-hidden="true">
                {{ $number }}
                <svg viewBox="0 0 16 16" fill="currentColor" class="cf-question-number-arrow"><path fill-rule="evenodd" d="M2 8a.75.75 0 0 1 .75-.75h8.69L8.22 4.03a.75.75 0 0 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 0 1-1.06-1.06l3.22-3.22H2.75A.75.75 0 0 1 2 8Z" clip-rule="evenodd" /></svg>
            </span>
        @endif

        <{{ $labelTag }}
            id="{{ $id }}-label"
            @if ($labelTarget) for="{{ $id }}" @endif
            @class(['cf-question-label', 'cf-sr-only' => $field->isLabelHidden()])
        >
            {{ $label }}@if ($isRequired)<span class="cf-question-required" aria-hidden="true">*</span>@endif
        </{{ $labelTag }}>

        @if (filled($description))
            <p id="{{ $id }}-description" class="cf-question-description">{{ $description }}</p>
        @endif
    </div>

    @if ($media)
        <figure class="cf-question-media">
            <img src="{{ $media['url'] }}" alt="{{ $media['alt'] }}" loading="lazy" />
        </figure>
    @endif

    <div class="cf-question-control">
        {{ $slot }}
    </div>

    <p
        id="{{ $id }}-error"
        role="alert"
        class="cf-question-error"
        @if (blank($errorMessage)) hidden @endif
    >{{ $errorMessage }}</p>
</div>
