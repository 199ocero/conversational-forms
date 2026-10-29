<?php

use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Questions\Checkbox;
use JaOcero\ConversationalForms\Questions\Dropdown;
use JaOcero\ConversationalForms\Questions\LegalConsent;
use JaOcero\ConversationalForms\Questions\Nps;
use JaOcero\ConversationalForms\Questions\Number;
use JaOcero\ConversationalForms\Questions\Phone;
use JaOcero\ConversationalForms\Questions\PictureChoice;
use JaOcero\ConversationalForms\Questions\Rating;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Questions\Website;
use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\TestConversation;
use Livewire\Features\SupportTesting\Testable;

function answerOf(Testable $livewire, string $field): mixed
{
    $livewire->submitConversation()->assertHasNoErrors();

    return TestConversation::$submissions[0]['data'][$field] ?? null;
}

describe('Website', function () {
    it('renders a URL input and validates the address', function () {
        conversation(Conversation::make([Website::make('site'), ShortText::make('next')]))
            ->assertSeeHtml('type="url"')
            ->assertSeeHtml('placeholder="https://"')
            ->set('data.site', 'not a url')
            ->advanceConversation('site')
            ->assertHasErrors(['data.site' => 'url'])
            ->set('data.site', 'https://filamentphp.com')
            ->advanceConversation('site')
            ->assertHasNoErrors();
    });
});

describe('Phone', function () {
    it('accepts common formats and saves the normalized number', function () {
        $livewire = conversation(Conversation::make([Phone::make('phone')]))
            ->assertSeeHtml('type="tel"')
            ->assertSeeHtml('autocomplete="tel"')
            ->set('data.phone', '+63 (917) 123-4567');

        expect(answerOf($livewire, 'phone'))->toBe('+639171234567');
    });

    it('only lets phone number characters be typed', function () {
        expect(Phone::make('phone')->getInputFilter())->toBe('^\\+?[0-9\\s().-]*$')
            ->and(ShortText::make('name')->getInputFilter())->toBeNull();
    });

    it('rejects text that is not a phone number', function () {
        conversation(Conversation::make([Phone::make('phone'), ShortText::make('next')]))
            ->set('data.phone', 'call me maybe')
            ->advanceConversation('phone')
            ->assertHasErrors(['data.phone' => 'regex']);
    });

    it('can keep the number as typed', function () {
        $livewire = conversation(Conversation::make([Phone::make('phone')->normalize(false)]))
            ->set('data.phone', '0917 123 4567');

        expect(answerOf($livewire, 'phone'))->toBe('0917 123 4567');
    });
});

describe('Number', function () {
    it('validates the range and saves a number', function () {
        $livewire = conversation(Conversation::make([Number::make('seats')->integer()->minValue(1)->maxValue(500), ShortText::make('next')]))
            ->assertSeeHtml('inputmode="numeric"')
            ->set('data.seats', '0')
            ->advanceConversation('seats')
            ->assertHasErrors(['data.seats' => 'min'])
            ->set('data.seats', '12.5')
            ->advanceConversation('seats')
            ->assertHasErrors(['data.seats' => 'integer'])
            ->set('data.seats', '12');

        expect(answerOf($livewire, 'seats'))->toBe(12);
    });

    it('only lets numbers be typed', function (Number $question, string $filter) {
        expect($question->getInputFilter())->toBe($filter);

        conversation(Conversation::make([$question]))->assertSeeHtml('x-on:beforeinput=');
    })->with([
        'whole numbers, no negatives' => [fn () => Number::make('seats')->integer()->minValue(1), '^\\d*$'],
        'whole numbers' => [fn () => Number::make('offset')->integer(), '^-?\\d*$'],
        'decimals' => [fn () => Number::make('budget'), '^-?\\d*(\\.\\d*)?$'],
        'decimals, no negatives' => [fn () => Number::make('budget')->minValue(0), '^\\d*(\\.\\d*)?$'],
    ]);

    it('saves decimals as floats', function () {
        $livewire = conversation(Conversation::make([Number::make('budget')]))
            ->assertSeeHtml('inputmode="decimal"')
            ->set('data.budget', '1500.50');

        expect(answerOf($livewire, 'budget'))->toBe(1500.5);
    });
});

describe('Dropdown', function () {
    it('renders an accessible combobox', function () {
        conversation(Conversation::make([Dropdown::make('country')->options(['ph' => 'Philippines', 'de' => 'Germany'])]))
            ->assertSeeHtml('role="combobox"')
            ->assertSeeHtml('aria-controls="form.conversation.cf-step-country.country-listbox"')
            ->assertSeeHtml('role="listbox"')
            ->assertSeeHtml('dropdownQuestionComponent');
    });

    it('only accepts its options', function () {
        conversation(Conversation::make([Dropdown::make('country')->options(['ph' => 'Philippines']), ShortText::make('next')]))
            ->set('data.country', 'xx')
            ->advanceConversation('country')
            ->assertHasErrors(['data.country' => 'in'])
            ->set('data.country', 'ph')
            ->advanceConversation('country')
            ->assertHasNoErrors();
    });
});

describe('Checkbox', function () {
    it('renders a tick box that defaults to unticked', function () {
        conversation(Conversation::make([Checkbox::make('updates')->checkboxLabel('Keep me updated')]))
            ->assertSeeHtml('role="checkbox"')
            ->assertSee('Keep me updated')
            ->assertSet('data.updates', false);
    });

    it('can require the box to be ticked', function () {
        conversation(Conversation::make([Checkbox::make('terms')->accepted(), ShortText::make('next')]))
            ->advanceConversation('terms')
            ->assertHasErrors(['data.terms' => 'accepted'])
            ->set('data.terms', true)
            ->advanceConversation('terms')
            ->assertHasNoErrors();
    });
});

describe('LegalConsent', function () {
    it('shows the terms and only lets a required consent continue when accepted', function () {
        conversation(Conversation::make([LegalConsent::make('consent')->terms('By continuing you agree to our terms.')->required(), ShortText::make('next')]))
            ->assertSee(['By continuing you agree to our terms.', 'I accept', "I don't accept"])
            ->assertSeeHtml('class="cf-terms"')
            ->set('data.consent', false)
            ->advanceConversation('consent')
            ->assertHasErrors(['data.consent' => 'accepted'])
            ->set('data.consent', true)
            ->advanceConversation('consent')
            ->assertHasNoErrors();
    });
});

describe('PictureChoice', function () {
    it('renders an image for each answer', function () {
        conversation(Conversation::make([
            PictureChoice::make('style')
                ->options(['modern' => 'Modern', 'classic' => 'Classic'])
                ->images(['modern' => 'https://example.com/modern.jpg', 'classic' => 'https://example.com/classic.jpg']),
        ]))
            ->assertSeeHtml('src="https://example.com/modern.jpg"')
            ->assertSeeHtml('aria-keyshortcuts="B"')
            ->assertSeeHtml('class="cf-choice-label"');
    });

    it('can hide labels visually but keep them for screen readers', function () {
        conversation(Conversation::make([PictureChoice::make('style')->options(['modern' => 'Modern'])->hiddenLabels()]))
            ->assertSeeHtml('class="cf-choice-label cf-sr-only"');
    });
});

describe('Rating', function () {
    it('renders accessible stars and validates the range', function () {
        conversation(Conversation::make([Rating::make('rating')->steps(5), ShortText::make('next')]))
            ->assertSeeHtml('aria-label="1 star out of 5"')
            ->assertSeeHtml('aria-label="5 stars out of 5"')
            ->set('data.rating', 6)
            ->advanceConversation('rating')
            ->assertHasErrors(['data.rating' => 'between'])
            ->set('data.rating', 4)
            ->advanceConversation('rating')
            ->assertHasNoErrors();
    });

    it('formats answers as a score', function () {
        expect(Rating::make('rating')->steps(10)->formatAnswer(8))->toBe('8/10');
    });

    it('rejects shapes and step counts it cannot render', function (Closure $configure) {
        $configure(Rating::make('rating'));
    })->with([
        'too few steps' => [fn (Rating $rating) => $rating->steps(2)->getSteps()],
        'unknown shape' => [fn (Rating $rating) => $rating->shape('circle')->getShape()],
    ])->throws(InvalidArgumentException::class);
});

describe('Nps', function () {
    it('is a 0–10 scale with labels', function () {
        conversation(Conversation::make([Nps::make('nps')]))
            ->assertSeeHtml('--cf-scale-steps: 11')
            ->assertSee(['0: Not likely at all', '10: Extremely likely']);
    });

    it('groups scores into categories', function (mixed $score, ?string $category) {
        expect(Nps::getCategory($score))->toBe($category);
    })->with([
        [0, Nps::DETRACTOR],
        [6, Nps::DETRACTOR],
        [7, Nps::PASSIVE],
        ['8', Nps::PASSIVE],
        [9, Nps::PROMOTER],
        [10, Nps::PROMOTER],
        [null, null],
    ]);
});
