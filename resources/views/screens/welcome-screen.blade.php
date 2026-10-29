@php
    use JaOcero\ConversationalForms\Support\Piping;

    $conversation = $getContainer()->getParentComponent();
@endphp

<div class="cf-screen cf-welcome-screen">
    @if (filled($title = Piping::render($getTitle(), $conversation)))
        <h2 class="cf-screen-title">{{ $title }}</h2>
    @endif

    @if (filled($description = Piping::render($getDescription(), $conversation)))
        <p class="cf-screen-description">{{ $description }}</p>
    @endif

    <div class="cf-step-actions">
        <button type="button" x-on:click="start()" class="cf-button">
            {{ $getButtonLabel() }}
        </button>

        <span class="cf-hint cf-hint-enter" aria-hidden="true">{!! __('conversational-forms::conversational-forms.navigation.press_enter') !!}</span>
    </div>
</div>
