@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $package = ConversationalFormsServiceProvider::getAssetPackageName();
    $key = $getKey();
    $path = $resolvePath();
    $steps = $getSteps();
    $welcomeScreen = $getWelcomeScreen();
    $endScreens = $getEndScreens();
    $completedEnding = $getLivewire()->completedConversationEnding ?? null;
    $isFullScreen = $isFullScreen();
    $isCompleted = filled($completedEnding);
    $theme = $getTheme();
    $fontUrl = \JaOcero\ConversationalForms\Themes\Fonts::stylesheetUrl($theme?->getFont());
@endphp

<div
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('conversation', $package) }}"
    x-load-css="[@js(FilamentAsset::getStyleHref('conversational-forms', $package))]"
    x-data="conversationComponent({
        autoAdvanceDelay: @js($getAutoAdvanceDelay()),
        hasWelcomeScreen: @js(filled($welcomeScreen)),
        isAutoAdvanceEnabled: @js($isAutoAdvanceEnabled()),
        completedEnding: @js($completedEnding),
        key: @js($key),
        path: @js($path->toArray()),
        submitMethod: @js($getSubmitMethod()),
        translations: @js([
            'progress' => __('conversational-forms::conversational-forms.navigation.progress'),
            'completed' => __('conversational-forms::conversational-forms.navigation.completed'),
            'yes' => __('conversational-forms::conversational-forms.questions.yes'),
            'no' => __('conversational-forms::conversational-forms.questions.no'),
        ]),
    })"
    @if ($isFullScreen)
        x-on:keydown.window="handleKeydown($event)"
    @else
        x-on:keydown="handleKeydown($event)"
    @endif
    x-on:cf-answered="handleAnswered($event)"
    x-on:cf-conversation-completed.window="handleCompleted($event.detail)"
    x-on:cf-conversation-invalid.window="handleInvalid($event.detail)"
    x-cloak
    wire:ignore.self
    data-cf-full-screen="{{ $isFullScreen ? 'true' : 'false' }}"
    @if ($theme)
        data-cf-theme="{{ $theme->isDark() ? 'dark' : 'light' }}"
        style="{{ $theme->toStyle() }}"
    @endif
    {{ $getExtraAttributeBag()->class(['cf-conversation', 'cf-full-screen' => $isFullScreen]) }}
>
    @if ($fontUrl)
        <link rel="stylesheet" href="{{ $fontUrl }}" />
    @endif

    @if ($hasProgressBar())
        <div
            role="progressbar"
            aria-label="{{ __('conversational-forms::conversational-forms.navigation.progress_label') }}"
            aria-valuemin="0"
            aria-valuemax="100"
            x-bind:aria-valuenow="Math.round(progress * 100)"
            class="cf-progress"
        >
            <div class="cf-progress-bar" x-bind:style="{ width: `${progress * 100}%` }"></div>
        </div>
    @endif

    <div class="cf-sr-only" aria-live="polite" aria-atomic="true" x-text="announcement"></div>

    @if ($welcomeScreen)
        <div x-show="phase === 'welcome'" class="cf-screen-container" data-cf-screen="welcome">
            {{ $welcomeScreen }}
        </div>
    @endif

    <div x-show="isInSteps()" class="cf-conversation-stage">
        @foreach ($steps as $step)
            {{-- Steps keep Filament's schema wrapper, so a single step can be partially re-rendered. --}}
            {!! $step->toSchemaHtml(isVisible: true) !!}
        @endforeach
    </div>

    @foreach ($endScreens as $endingName => $endScreen)
        <div
            x-show="phase === 'ended' && ending === @js($endingName)"
            tabindex="-1"
            class="cf-screen-container"
            data-cf-screen="end"
            data-cf-ending="{{ $endingName }}"
        >
            {{ $endScreen }}
        </div>
    @endforeach

    @if ($hasNavigationButtons())
        <div x-show="phase === 'steps'" class="cf-navigation">
            <button
                type="button"
                x-on:click="previous()"
                x-bind:disabled="! history.length"
                aria-label="{{ __('conversational-forms::conversational-forms.navigation.previous') }}"
                class="cf-navigation-button"
            >
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9.47 6.47a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 1 1-1.06 1.06L10 8.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06l4.25-4.25Z" clip-rule="evenodd" /></svg>
            </button>

            <button
                type="button"
                x-on:click="next()"
                x-bind:disabled="isAdvancing"
                aria-label="{{ __('conversational-forms::conversational-forms.navigation.next') }}"
                class="cf-navigation-button"
            >
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
            </button>
        </div>
    @endif
</div>
