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
    'components/choice-question.js',
    'conversational-forms.css',
]);

it('loads the package translations', function () {
    expect(__('conversational-forms::conversational-forms.navigation.next'))->toBe('Next question');
});

it('versions asset URLs by their contents, so a rebuilt file is never served from a stale cache', function () {
    $package = ConversationalFormsServiceProvider::getAssetPackageName();
    $hash = hash_file('xxh128', __DIR__ . '/../resources/dist/components/conversation.js');

    expect(FilamentAsset::getAlpineComponentSrc('conversation', $package))->toEndWith("?v={$hash}")
        ->and(FilamentAsset::getStyleHref('conversational-forms', $package))
        ->toEndWith('?v=' . hash_file('xxh128', __DIR__ . '/../resources/dist/conversational-forms.css'));
});
