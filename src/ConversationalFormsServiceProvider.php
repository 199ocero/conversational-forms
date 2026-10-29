<?php

namespace JaOcero\ConversationalForms;

use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;
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
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command->askToStarRepoOnGitHub('199ocero/conversational-forms');
            });
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            $this->getAssets(),
            static::getAssetPackageName()
        );

        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components', static::$viewNamespace);

        Testable::mixin(new TestsConversations);
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
            Css::make('conversational-forms', __DIR__ . '/../resources/dist/conversational-forms.css')->loadedOnRequest(),
        ];
    }
}
