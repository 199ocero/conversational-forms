@php
    use JaOcero\ConversationalForms\Support\Piping;

    $conversation = $getConversation();
    $name = $getName();
    $label = Piping::render($getLabel(), $conversation);
    $description = Piping::render($getDescription(), $conversation);
    $number = $conversation->getStepNumber($name);
    $headingId = "cf-group-{$getLivewire()->getId()}-{$name}";
@endphp

<x-conversational-forms::step
    :step="$schemaComponent"
    class="cf-question-group"
    role="group"
    :aria-labelledby="filled($label) ? $headingId : null"
>
    @if (filled($label) || filled($description))
        <div class="cf-question-header">
            @if (filled($label))
                @if ($number)
                    <span class="cf-question-number" aria-hidden="true">
                        {{ $number }}
                        <svg viewBox="0 0 16 16" fill="currentColor" class="cf-question-number-arrow"><path fill-rule="evenodd" d="M2 8a.75.75 0 0 1 .75-.75h8.69L8.22 4.03a.75.75 0 0 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 0 1-1.06-1.06l3.22-3.22H2.75A.75.75 0 0 1 2 8Z" clip-rule="evenodd" /></svg>
                    </span>
                @endif

                <h2 id="{{ $headingId }}" class="cf-question-label">{{ $label }}</h2>
            @endif

            @if (filled($description))
                <p class="cf-question-description">{{ $description }}</p>
            @endif
        </div>
    @endif

    <div class="cf-question-group-fields">
        {{ $getChildSchema() }}
    </div>
</x-conversational-forms::step>
