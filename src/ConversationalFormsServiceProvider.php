<?php

namespace JaOcero\ConversationalForms;

use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use JaOcero\ConversationalForms\Definitions\BuiltInQuestionTypes;
use JaOcero\ConversationalForms\Definitions\QuestionTypeRegistry;
use JaOcero\ConversationalForms\Http\Controllers\EmbedScriptController;
use JaOcero\ConversationalForms\Livewire\ConversationalFormPage;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Support\Assets\AlpineComponent;
use JaOcero\ConversationalForms\Support\Assets\Css;
use JaOcero\ConversationalForms\Testing\TestsConversations;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ConversationalFormsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'conversational-forms';

    public static string $viewNamespace = 'conversational-forms';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasMigrations(['create_conversational_forms_tables', 'create_conversational_form_responses_table'])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('199ocero/conversational-forms');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(
            QuestionTypeRegistry::class,
            fn (): QuestionTypeRegistry => (new QuestionTypeRegistry)->register(...BuiltInQuestionTypes::all()),
        );
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            $this->getAssets(),
            static::getAssetPackageName()
        );

        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components', static::$viewNamespace);

        Testable::mixin(new TestsConversations);

        $this->registerRoutes();
    }

    /**
     * The public pages of builder-made conversations.
     */
    protected function registerRoutes(): void
    {
        if ((! config('conversational-forms.routes.enabled', true)) || $this->app->routesAreCached()) {
            return;
        }

        Route::middleware(config('conversational-forms.routes.middleware', ['web']))
            ->prefix(config('conversational-forms.routes.prefix', 'f'))
            ->group(function (): void {
                Route::get('embed.js', EmbedScriptController::class)->name('conversational-forms.embed-script');
                Route::livewire('{slug}', ConversationalFormPage::class)->name('conversational-forms.show');
            });
    }

    /**
     * A link to a published conversation, or to a preview of its draft.
     */
    public static function getFormUrl(ConversationalForm $form, bool $preview = false): string
    {
        if ($preview) {
            return URL::temporarySignedRoute('conversational-forms.show', now()->addHours(2), ['slug' => $form->slug, 'preview' => 1]);
        }

        return route('conversational-forms.show', ['slug' => $form->slug]);
    }

    public static function getAssetPackageName(): string
    {
        return 'jaocero/conversational-forms';
    }

    /**
     * Every asset is loaded on request by the conversation's elements (`x-load`
     * and `x-load-css`), so pages without a conversation never download them.
     *
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            AlpineComponent::make('conversation', __DIR__ . '/../resources/dist/components/conversation.js'),
            AlpineComponent::make('choice-question', __DIR__ . '/../resources/dist/components/choice-question.js'),
            AlpineComponent::make('dropdown-question', __DIR__ . '/../resources/dist/components/dropdown-question.js'),
            AlpineComponent::make('segments-question', __DIR__ . '/../resources/dist/components/segments-question.js'),
            AlpineComponent::make('ranking-question', __DIR__ . '/../resources/dist/components/ranking-question.js'),
            AlpineComponent::make('file-upload-question', __DIR__ . '/../resources/dist/components/file-upload-question.js'),
            AlpineComponent::make('signature-question', __DIR__ . '/../resources/dist/components/signature-question.js'),
            AlpineComponent::make('logic-map', __DIR__ . '/../resources/dist/components/logic-map.js'),
            AlpineComponent::make('media-question', __DIR__ . '/../resources/dist/components/media-question.js'),
            AlpineComponent::make('builder-editor', __DIR__ . '/../resources/dist/components/builder-editor.js'),
            Css::make('conversational-forms', __DIR__ . '/../resources/dist/conversational-forms.css')->loadedOnRequest(),
        ];
    }
}
