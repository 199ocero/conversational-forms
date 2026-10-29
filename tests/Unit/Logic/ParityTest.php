<?php

use JaOcero\ConversationalForms\Logic\ConditionGroup;
use JaOcero\ConversationalForms\Logic\Enums\Operator;

$cases = json_decode(file_get_contents(__DIR__ . '/../../Fixtures/logic/cases.json'), associative: true);

dataset('operator cases', array_combine(
    array_map(fn (array $case): string => "{$case['op']}: {$case['note']}", $cases['operators']),
    array_map(fn (array $case): array => [$case], $cases['operators']),
));

dataset('group cases', array_combine(
    array_map(fn (array $case): string => $case['note'], $cases['groups']),
    array_map(fn (array $case): array => [$case], $cases['groups']),
));

it('evaluates operators exactly like the browser', function (array $case) {
    expect(Operator::resolve($case['op'])->evaluate($case['answer'], $case['value']))->toBe($case['expected']);
})->with('operator cases');

it('evaluates condition groups exactly like the browser', function (array $case) {
    expect(ConditionGroup::fromArray($case['conditions'])->matches($case['answers'], $case['variables']))->toBe($case['expected']);
})->with('group cases');
