<?php

use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Components\Statement;
use JaOcero\ConversationalForms\Questions\Email;
use JaOcero\ConversationalForms\Questions\MultipleChoice;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Questions\YesNo;
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Support\Piping;

it('replaces tokens with the answer and keeps it in sync in the browser', function () {
    conversation(Conversation::make([
        ShortText::make('name'),
        Email::make('email')->label('Nice to meet you, {name}!'),
    ]))
        ->set('data.name', 'Jay')
        ->call('$refresh')
        ->assertSeeHtml('Nice to meet you, <span class="cf-pipe" x-text="pipe(&#039;data.name&#039;, null)">Jay</span>!');
});

it('never renders an answer as HTML', function () {
    conversation(Conversation::make([
        ShortText::make('name'),
        Email::make('email')->label('Hi {name}'),
    ]))
        ->set('data.name', '<img src=x onerror=alert(1)>')
        ->call('$refresh')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>')
        ->assertSeeHtml('&lt;img src=x onerror=alert(1)&gt;');
});

it('escapes the label itself before replacing tokens', function () {
    conversation(Conversation::make([
        ShortText::make('name'),
        Email::make('email')->label('<b>Hi</b> {name}'),
    ]))->assertSeeHtml('&lt;b&gt;Hi&lt;/b&gt; <span class="cf-pipe"');
});

it('shows choice labels rather than values', function () {
    conversation(Conversation::make([
        MultipleChoice::make('plan')->options(['enterprise' => 'Enterprise']),
        YesNo::make('newsletter'),
        Statement::make('summary')->title('{plan} plan, newsletter: {newsletter}'),
    ]))
        ->set('data.plan', 'enterprise')
        ->set('data.newsletter', false)
        ->call('$refresh')
        ->assertSeeHtml('>Enterprise</span> plan, newsletter: <span')
        ->assertSeeHtml('\\u0022enterprise\\u0022:\\u0022Enterprise\\u0022')
        ->assertSeeHtml('>No</span>');
});

it('pipes into descriptions and screens', function () {
    conversation(Conversation::make([
        ShortText::make('name')->description('Hello {name}'),
    ])->endScreen(EndScreen::make()->title('Thanks, {name}!')))
        ->assertSeeHtml('Thanks, <span class="cf-pipe"');
});

it('leaves text without tokens untouched', function () {
    expect(Piping::render('No tokens here', null))->toBe('No tokens here')
        ->and(Piping::hasTokens('Hi {name}'))->toBeTrue()
        ->and(Piping::hasTokens('Braces { alone }'))->toBeFalse();
});
