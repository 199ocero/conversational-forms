@php
    $state = $getState();
    $steps = $getSteps();
    $value = is_numeric($state) ? (int) $state : null;
@endphp

<div
    {{ $getExtraAttributeBag()->class(['cf-rating-column fi-ta-text px-3 py-4']) }}
    @if ($value !== null) aria-label="{{ trans_choice('conversational-forms::conversational-forms.questions.rating_value', $value, ['value' => $value, 'max' => $steps]) }}" @endif
    style="display: flex; gap: 0.125rem"
>
    @if ($value !== null)
        @foreach (range(1, $steps) as $step)
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 1.25rem; height: 1.25rem; color: {{ $step <= $value ? 'var(--warning-400)' : 'var(--gray-300)' }}"><path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401Z" clip-rule="evenodd" /></svg>
        @endforeach
    @endif
</div>
