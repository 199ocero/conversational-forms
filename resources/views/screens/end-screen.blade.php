<div class="cf-screen cf-end-screen">
    <h2 class="cf-screen-title">{{ $getTitle() }}</h2>

    @if (filled($description = $getDescription()))
        <p class="cf-screen-description">{{ $description }}</p>
    @endif

    @if (filled($buttonLabel = $getButtonLabel()) && filled($buttonUrl = $getButtonUrl()))
        <div class="cf-step-actions">
            <a href="{{ $buttonUrl }}" class="cf-button">{{ $buttonLabel }}</a>
        </div>
    @endif
</div>
