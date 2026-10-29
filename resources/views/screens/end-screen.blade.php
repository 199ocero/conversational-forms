@php
    use JaOcero\ConversationalForms\Support\Piping;

    $conversation = $getContainer()->getParentComponent();
@endphp

<div class="cf-screen cf-end-screen">
    <h2 class="cf-screen-title">{{ Piping::render($getTitle(), $conversation) }}</h2>

    @if (filled($description = Piping::render($getDescription(), $conversation)))
        <p class="cf-screen-description">{{ $description }}</p>
    @endif

    @if (filled($buttonLabel = $getButtonLabel()) && filled($buttonUrl = $getButtonUrl()))
        <div class="cf-step-actions">
            <a href="{{ $buttonUrl }}" class="cf-button">{{ $buttonLabel }}</a>
        </div>
    @endif
</div>
