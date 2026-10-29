<?php

use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Logic\PathResolver;

function resolvePath(array $answers, array $rules = [], ?array $steps = null, ?array $stepFields = null): array
{
    $steps ??= ['name', 'plan', 'company_size', 'recommend', 'notes'];
    $stepFields ??= array_combine($steps, array_map(fn (string $step): array => [$step], $steps));

    return (new PathResolver)->resolve($steps, $rules, $stepFields, $answers)->toArray();
}

$planRules = [
    'plan' => [
        Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'),
        Logic::always()->jumpTo('recommend'),
    ],
];

it('visits every step in order without logic', function () {
    expect(resolvePath([]))->toBe(['name', 'plan', 'company_size', 'recommend', 'notes']);
});

it('follows the first matching jump', function () use ($planRules) {
    expect(resolvePath(['plan' => 'enterprise'], $planRules))->toBe(['name', 'plan', 'company_size', 'recommend', 'notes'])
        ->and(resolvePath(['plan' => 'starter'], $planRules))->toBe(['name', 'plan', 'recommend', 'notes']);
});

it('uses the always rule as a fallback while a question is unanswered', function () use ($planRules) {
    expect(resolvePath([], $planRules))->toBe(['name', 'plan', 'recommend', 'notes']);
});

it('ends the conversation early', function () {
    $rules = ['plan' => [Logic::when('plan', 'is', 'none')->end()]];

    expect(resolvePath(['plan' => 'none'], $rules))->toBe(['name', 'plan'])
        ->and(resolvePath(['plan' => 'starter'], $rules))->toBe(['name', 'plan', 'company_size', 'recommend', 'notes']);
});

it('ignores answers to steps that are no longer on the path', function () use ($planRules) {
    $rules = [
        ...$planRules,
        'recommend' => [Logic::when('company_size', 'is', 'large')->end()],
    ];

    // The respondent first chose enterprise and answered "large", then changed their plan.
    expect(resolvePath(['plan' => 'starter', 'company_size' => 'large'], $rules))
        ->toBe(['name', 'plan', 'recommend', 'notes']);
});

it('ignores rules without a target and stale backward targets', function () {
    $rules = [
        'plan' => [Logic::when('plan', 'is', 'starter'), Logic::always()->jumpTo('name')],
    ];

    expect(resolvePath(['plan' => 'starter'], $rules))->toBe(['name', 'plan', 'company_size', 'recommend', 'notes']);
});

it('reports progress along the path', function () use ($planRules) {
    $path = (new PathResolver)->resolve(
        ['name', 'plan', 'company_size', 'recommend'],
        $planRules,
        [],
        ['plan' => 'starter'],
    );

    expect($path->toArray())->toBe(['name', 'plan', 'recommend'])
        ->and($path->after('plan'))->toBe('recommend')
        ->and($path->after('recommend'))->toBeNull()
        ->and($path->isLast('recommend'))->toBeTrue()
        ->and($path->progressAfter('name'))->toBe(0.3333)
        ->and($path->progressAfter('recommend'))->toBe(1.0)
        ->and($path->progressAfter('company_size'))->toBe(0.0);
});
