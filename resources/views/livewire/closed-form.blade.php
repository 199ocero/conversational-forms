@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;
@endphp

<main class="cf-public-page">
    <div
        x-data
        x-load-css="[@js(FilamentAsset::getStyleHref('conversational-forms', ConversationalFormsServiceProvider::getAssetPackageName()))]"
        class="cf-conversation cf-full-screen"
    >
        <div class="cf-screen-container">
            <section class="cf-screen" role="status">
                <h1 class="cf-screen-title">{{ __('conversational-forms::conversational-forms.public.closed_title') }}</h1>
                <p class="cf-screen-description">{{ __('conversational-forms::conversational-forms.public.closed_description') }}</p>
            </section>
        </div>
    </div>
</main>
