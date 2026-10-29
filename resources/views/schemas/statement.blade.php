@php
    use JaOcero\ConversationalForms\Support\Piping;

    $conversation = $getConversation();
    $title = Piping::render($getTitle(), $conversation);
    $description = Piping::render($getDescription(), $conversation);
@endphp

<x-conversational-forms::step :step="$schemaComponent" class="cf-statement">
    <div class="cf-question-header">
        @if (filled($title))
            <h2 class="cf-question-label">{{ $title }}</h2>
        @endif

        @if (filled($description))
            <p class="cf-question-description">{{ $description }}</p>
        @endif
    </div>
</x-conversational-forms::step>
