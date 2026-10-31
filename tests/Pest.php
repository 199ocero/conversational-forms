<?php

use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Artisan;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\BrowserConversationPage;
use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\TestConversation;
use JaOcero\ConversationalForms\Tests\TestCase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(TestCase::class)->in(__DIR__);

/**
 * @param  array<Component> | Component  $components
 */
function conversation(array | Component $components): Testable
{
    TestConversation::$components = fn (): array => is_array($components) ? $components : [$components];
    TestConversation::$submissions = [];

    return Livewire::test(TestConversation::class);
}

function conversationIn(Testable $livewire): Conversation
{
    return $livewire->instance()->form->getComponent('conversation');
}

/**
 * @param  array<string, mixed>  $definition
 */
function definitionConversation(array $definition): Testable
{
    return conversation(ConversationDefinition::make($definition)->toConversation());
}

/**
 * Sort associative keys so definitions compare regardless of key order
 * (MySQL's JSON columns store object keys in their own order).
 */
function canonical(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    $value = array_map(canonical(...), $value);

    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
}

/*
|--------------------------------------------------------------------------
| Browser tests
|--------------------------------------------------------------------------
|
| `tests/Browser` runs in Chromium through the Pest browser plugin (PHP 8.4+,
| `vendor/bin/pest --testsuite=Browser`). Pages are served by the test's own
| process, from Testbench's public directory, so Filament's and the package's
| assets are published there once per run.
|
*/

pest()
    ->beforeEach(function (): void {
        static $hasPublishedAssets = false;

        if (! $hasPublishedAssets) {
            Artisan::call('filament:assets');
            $hasPublishedAssets = true;
        }

        BrowserConversationPage::$submissions = [];
    })
    ->group('browser')
    ->in('Browser');

/**
 * Open a page with a conversation and wait for its script to load: the
 * conversation's keyboard handling only exists once Alpine has started it.
 */
function visitConversation(string $url): mixed
{
    return waitUntil(visit($url), "document.querySelector('.cf-conversation')?._x_dataStack");
}

/**
 * Press a key where the focus is, as a person using the keyboard would.
 */
function pressKey(mixed $page, string $key): mixed
{
    $hasFocus = $page->script('() => document.activeElement !== null && document.activeElement !== document.body');

    return $page->keys($hasFocus ? ':focus' : ':root', $key);
}

/**
 * Wait until a JavaScript expression is truthy, then return the page.
 */
function waitUntil(mixed $page, string $expression, float $seconds = 5): mixed
{
    $deadline = microtime(true) + $seconds;

    while (! $page->script("() => Boolean({$expression})")) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException("Timed out waiting for [{$expression}].");
        }

        usleep(50_000);
    }

    return $page;
}

/**
 * Wait until the given step is the active one and its transition has settled.
 */
function waitForStep(mixed $page, string $step): mixed
{
    return waitUntil($page, "document.querySelector('.cf-step.cf-active')?.dataset.cfStep === " . json_encode($step));
}

/**
 * Wait until the focus is inside the given step (it moves there one frame
 * after the step is shown).
 */
function waitForFocusIn(mixed $page, string $step): mixed
{
    return waitUntil($page, "document.activeElement?.closest('[data-cf-step]')?.dataset.cfStep === " . json_encode($step));
}
