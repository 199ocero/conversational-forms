<?php

use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\BrowserConversationPage;

it('can be answered from start to finish with the keyboard alone', function (): void {
    $page = visitConversation('/onboarding')->assertSee('Get started in a minute');

    pressKey($page, 'Enter');
    waitForStep($page, 'name');
    waitUntil($page, "document.activeElement?.matches('input')");

    $page->keys(':focus', ['J', 'a', 'y', 'Enter']);
    waitForStep($page, 'plan')->assertSee('Which plan, Jay?');
    waitForFocusIn($page, 'plan');

    // A letter picks the choice and moves on by itself.
    pressKey($page, 'c');
    waitForStep($page, 'company');
    waitForFocusIn($page, 'company');

    $page->keys(':focus', ['A', 'c', 'm', 'e', 'Enter']);
    waitForStep($page, 'recommend');

    pressKey($page, '9');
    waitForStep($page, 'newsletter');

    pressKey($page, 'y');
    waitForStep($page, 'notes');

    // Shift + Enter adds a new line; Enter submits.
    $page->keys(':focus', ['H', 'i', 'Shift+Enter', 'o', 'k', 'Enter']);

    waitUntil($page, "document.querySelector('[data-cf-screen=end]')?.offsetParent !== null");
    $page->assertSee('Thanks, Jay!')->assertNoJavaScriptErrors();

    expect(BrowserConversationPage::$submissions)->toHaveCount(1)
        ->and(BrowserConversationPage::$submissions[0]['data'])->toMatchArray([
            'name' => 'Jay',
            'plan' => 'enterprise',
            'company' => 'Acme',
            'recommend' => 9,
            'newsletter' => true,
            'notes' => "Hi\nok",
        ])
        ->and(BrowserConversationPage::$submissions[0]['result']->path)->toContain('company');
});

it('keeps the step and focuses the answer when it is invalid', function (): void {
    $page = visitConversation('/onboarding');

    pressKey($page, 'Enter');
    waitForStep($page, 'name');

    pressKey($page, 'Enter');
    waitUntil($page, "document.activeElement?.getAttribute('aria-invalid') === 'true'");

    waitForFocusIn($page, 'name');
    $page->assertSee('required');
    expect(BrowserConversationPage::$submissions)->toBeEmpty();
});

it('skips questions that logic jumps over, and goes back with the keyboard', function (): void {
    $page = visitConversation('/onboarding');

    pressKey($page, 'Enter');
    waitForStep($page, 'name');
    $page->keys(':focus', ['J', 'o', 'Enter']);
    waitForStep($page, 'plan');

    pressKey($page, 'a');
    waitForStep($page, 'recommend');

    // The progress follows the shorter path: Company was skipped.
    $progress = (int) $page->attribute('[role=progressbar]', 'aria-valuenow');
    expect($progress)->toBeGreaterThan(0)->toBeLessThan(100);

    pressKey($page, 'Alt+ArrowUp');
    waitForStep($page, 'plan');
    waitForFocusIn($page, 'plan');

    pressKey($page, 'Alt+ArrowUp');
    waitForStep($page, 'name');
    $page->assertValue('[data-cf-step=name] input', 'Jo');
});

it('has no accessibility issues on any screen', function (): void {
    $page = visitConversation('/onboarding');
    $page->assertNoAccessibilityIssues();

    pressKey($page, 'Enter');
    waitForStep($page, 'name');
    $page->assertNoAccessibilityIssues();

    $page->keys(':focus', ['J', 'o', 'Enter']);
    waitForStep($page, 'plan');
    $page->assertNoAccessibilityIssues();

    pressKey($page, 'c');
    waitForStep($page, 'company');
    $page->keys(':focus', ['A', 'Enter']);
    waitForStep($page, 'recommend');
    $page->assertNoAccessibilityIssues();

    pressKey($page, '7');
    waitForStep($page, 'newsletter');
    $page->assertNoAccessibilityIssues();

    pressKey($page, 'n');
    waitForStep($page, 'notes');
    $page->assertNoAccessibilityIssues();

    pressKey($page, 'Enter');
    waitUntil($page, "document.querySelector('[data-cf-screen=end]')?.offsetParent !== null");
    $page->assertNoAccessibilityIssues();
});

it('has no accessibility issues in dark mode', function (): void {
    $page = waitUntil(visit('/onboarding')->inDarkMode(), "document.querySelector('.cf-conversation')?._x_dataStack");

    pressKey($page, 'Enter');
    waitForStep($page, 'name');

    $page->assertNoAccessibilityIssues();
});

it('fits a phone screen', function (): void {
    $page = waitUntil(visit('/onboarding')->on()->iPhone15(), "document.querySelector('.cf-conversation')?._x_dataStack");

    $page->press('Start');
    waitForStep($page, 'name');

    $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth')
        ->assertNoAccessibilityIssues();

    $page->type('[data-cf-step=name] input', 'Jay');
    $page->press('[data-cf-step=name] .cf-button');
    waitForStep($page, 'plan');

    // Touch targets are at least 44 × 44 px once the step has settled.
    waitUntil($page, "[...document.querySelectorAll('[data-cf-step=plan] [data-cf-choice], [data-cf-step=plan] .cf-button, .cf-navigation-button')].every((target) => target.getBoundingClientRect().height >= 44 && target.getBoundingClientRect().width >= 44)");
});
