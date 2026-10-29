<?php

use Filament\Support\Facades\FilamentAsset;
use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

it('registers the conversation Alpine component under the package name', function () {
    $src = FilamentAsset::getAlpineComponentSrc('conversation', ConversationalFormsServiceProvider::getAssetPackageName());

    expect($src)->toContain('/js/jaocero/conversational-forms/components/conversation.js');
});

it('registers the stylesheet so it is only loaded on request', function () {
    $stylesheet = collect(FilamentAsset::getStyles([ConversationalFormsServiceProvider::getAssetPackageName()]))
        ->firstWhere(fn ($asset): bool => $asset->getId() === 'conversational-forms');

    expect($stylesheet)->not->toBeNull()
        ->and($stylesheet->isLoadedOnRequest())->toBeTrue()
        ->and(FilamentAsset::getStyleHref('conversational-forms', ConversationalFormsServiceProvider::getAssetPackageName()))
        ->toContain('/css/jaocero/conversational-forms/conversational-forms.css');
});

it('ships prebuilt assets with the package', function (string $path) {
    expect(__DIR__ . "/../resources/dist/{$path}")->toBeFile();
})->with([
    'components/conversation.js',
    'conversational-forms.css',
]);

it('loads the package translations', function () {
    expect(__('conversational-forms::conversational-forms.navigation.next'))->toBe('Next');
});
