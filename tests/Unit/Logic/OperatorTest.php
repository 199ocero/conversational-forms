<?php

use JaOcero\ConversationalForms\Logic\Enums\Operator;
use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;

enum OperatorTestPlan: string
{
    case Enterprise = 'enterprise';
}

it('compares answers with is and is not', function (mixed $answer, mixed $value, bool $is) {
    expect(Operator::Is->evaluate($answer, $value))->toBe($is)
        ->and(Operator::IsNot->evaluate($answer, $value))->toBe(! $is);
})->with([
    'equal strings' => ['enterprise', 'enterprise', true],
    'different strings' => ['starter', 'enterprise', false],
    'numeric string and integer' => ['7', 7, true],
    'integer and equal float' => [7, 7.0, true],
    'different numbers' => ['7', 8, false],
    'true and true' => [true, true, true],
    'false and false' => [false, false, true],
    'false is not an unanswered question' => [null, false, false],
    'empty string counts as unanswered' => ['', null, true],
    'backed enum value' => ['enterprise', OperatorTestPlan::Enterprise, true],
]);

it('resolves operators from their value and common symbols', function (string $input, Operator $expected) {
    expect(Operator::resolve($input))->toBe($expected);
})->with([
    ['is', Operator::Is],
    ['=', Operator::Is],
    ['==', Operator::Is],
    ['is_not', Operator::IsNot],
    ['!=', Operator::IsNot],
]);

it('rejects unknown operators', function () {
    Operator::resolve('roughly');
})->throws(InvalidLogicException::class, 'The logic operator [roughly] is not supported.');
