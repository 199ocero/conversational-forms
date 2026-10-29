<?php

namespace JaOcero\ConversationalForms;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
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
    }

    public static function getAssetPackageName(): string
    {
        return 'jaocero/conversational-forms';
    }

    /**
     * Both assets are loaded on request by the conversation root element
     * (`x-load` and `x-load-css`), so pages without a conversation never download them.
     *
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            AlpineComponent::make('conversation', __DIR__ . '/../resources/dist/components/conversation.js'),
            Css::make('conversational-forms', __DIR__ . '/../resources/dist/conversational-forms.css')->loadedOnRequest(),
        ];
    }
}
