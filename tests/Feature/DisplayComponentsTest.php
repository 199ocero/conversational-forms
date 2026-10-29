<?php

use JaOcero\ConversationalForms\Infolists\Components\AnswerEntry;
use JaOcero\ConversationalForms\Questions\MultipleChoice;
use JaOcero\ConversationalForms\Questions\Rating;
use JaOcero\ConversationalForms\Questions\YesNo;
use JaOcero\ConversationalForms\Tables\Columns\AnswerColumn;
use JaOcero\ConversationalForms\Tables\Columns\NpsColumn;

function plans(): MultipleChoice
{
    return MultipleChoice::make('plan')->options(['starter' => 'Starter', 'enterprise' => 'Enterprise']);
}

it('formats answers with their question', function (mixed $answer, string $expected) {
    $plans = plans();

    expect(AnswerColumn::make('plan')->question($plans)->formatAnswer($answer))->toBe($expected)
        ->and(AnswerEntry::make('plan')->question($plans)->formatAnswer($answer))->toBe($expected);
})->with([
    'single choice' => ['enterprise', 'Enterprise'],
    'several choices' => [['starter', 'enterprise'], 'Starter, Enterprise'],
    'an "Other" answer' => ['Agency plan', 'Agency plan'],
    'no answer' => [null, ''],
]);

it('formats other answers sensibly', function () {
    expect(AnswerColumn::make('newsletter')->question(YesNo::make('newsletter'))->formatAnswer(true))->toBe('Yes')
        ->and(AnswerColumn::make('rating')->question(Rating::make('rating'))->formatAnswer(4))->toBe('4/5')
        ->and(AnswerColumn::make('notes')->formatAnswer(['a', null, 'b']))->toBe('a, b');
});

it('colors NPS scores by category', function (int $score, string $color) {
    expect(NpsColumn::make('nps')->getColor($score))->toBe($color);
})->with([
    [3, 'danger'],
    [7, 'warning'],
    [10, 'success'],
]);
