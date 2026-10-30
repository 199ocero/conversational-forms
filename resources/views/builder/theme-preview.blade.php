@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;
    use JaOcero\ConversationalForms\Themes\Fonts;

    $fontUrl = Fonts::stylesheetUrl($theme?->getFont());
    $issues = $theme?->contrastIssues() ?? [];
@endphp

<div style="display: grid; gap: 0.75rem">
    <p style="margin: 0; font-size: 0.875rem; font-weight: 500">{{ __('conversational-forms::conversational-forms.themes.preview') }}</p>

    @if ($fontUrl)
        <link rel="stylesheet" href="{{ $fontUrl }}" />
    @endif

    {{-- A sample question drawn with the conversation's own variables. --}}
    <div
        x-data
        x-load-css="[@js(FilamentAsset::getStyleHref('conversational-forms', ConversationalFormsServiceProvider::getAssetPackageName()))]"
        @if ($theme) data-cf-theme="{{ $theme->isDark() ? 'dark' : 'light' }}" style="{{ $theme->toStyle() }}" @endif
        class="cf-conversation cf-theme-preview"
    >
        <p class="cf-theme-preview-question"><span class="cf-theme-preview-number">1 →</span> Which plan suits you best?</p>
        <p class="cf-theme-preview-description">Pick one; you can change it later.</p>

        <div class="cf-theme-preview-choices">
            <span class="cf-theme-preview-choice"><kbd>A</kbd> Starter</span>
            <span class="cf-theme-preview-choice cf-selected"><kbd>B</kbd> Team</span>
        </div>

        <p class="cf-theme-preview-answer">Typed answers look like this</p>

        <span class="cf-theme-preview-button">OK ✓</span>
    </div>

    @if (! $theme)
        <p style="margin: 0; font-size: 0.8125rem; opacity: 0.7">{{ __('conversational-forms::conversational-forms.themes.follow_panel_help') }}</p>
    @endif

    @if (filled($issues))
        <div role="status" style="padding: 0.75rem 1rem; border-radius: 0.75rem; font-size: 0.8125rem; background: color-mix(in oklab, var(--warning-500, orange) 12%, transparent)">
            <p style="margin: 0 0 0.25rem; font-weight: 600">{{ __('conversational-forms::conversational-forms.themes.contrast_title') }}</p>
            <ul style="margin: 0; padding-inline-start: 1.25rem; list-style: disc">
                @foreach ($issues as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
