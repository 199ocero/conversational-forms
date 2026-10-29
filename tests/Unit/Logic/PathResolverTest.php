<?php

use JaOcero\ConversationalForms\Logic\ConversationPath;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Logic\PathResolver;

/**
 * @param  array<string, mixed>  $answers
 */
function resolvePath(array $answers, array $rules = [], ?array $steps = null, array $stepConditions = [], array $variables = [], ?string $defaultEnding = 'default'): ConversationPath
{
    $steps ??= ['name', 'plan', 'company_size', 'recommend', 'notes'];
    $stepFields = array_combine($steps, array_map(fn (string $step): array => [$step], $steps));

    return (new PathResolver)->resolve($steps, $rules, $stepFields, $answers, $stepConditions, $variables, $defaultEnding);
}

$planRules = [
    'plan' => [
        Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'),
        Logic::always()->jumpTo('recommend'),
    ],
];

it('visits every step in order without logic', function () {
    expect(resolvePath([])->toArray())->toBe(['name', 'plan', 'company_size', 'recommend', 'notes'])
        ->and(resolvePath([])->ending)->toBe('default');
});

it('follows the first matching jump', function () use ($planRules) {
    expect(resolvePath(['plan' => 'enterprise'], $planRules)->toArray())->toBe(['name', 'plan', 'company_size', 'recommend', 'notes'])
        ->and(resolvePath(['plan' => 'starter'], $planRules)->toArray())->toBe(['name', 'plan', 'recommend', 'notes']);
});

it('uses the always rule as a fallback while a question is unanswered', function () use ($planRules) {
    expect(resolvePath([], $planRules)->toArray())->toBe(['name', 'plan', 'recommend', 'notes']);
});

it('ends early, with the default or a named ending', function () {
    $rules = ['plan' => [
        Logic::when('plan', 'is', 'none')->end(),
        Logic::when('plan', 'is', 'student')->endWith('student_offer'),
    ]];

    expect(resolvePath(['plan' => 'none'], $rules))->toMatchObject(['steps' => ['name', 'plan'], 'ending' => 'default'])
        ->and(resolvePath(['plan' => 'student'], $rules))->toMatchObject(['steps' => ['name', 'plan'], 'ending' => 'student_offer'])
        ->and(resolvePath(['plan' => 'starter'], $rules)->steps)->toHaveCount(5);
});

it('calculates variables along the path only', function () {
    $rules = [
        'name' => [Logic::always()->add('score', 1)],
        'plan' => [Logic::when('plan', 'is', 'enterprise')->add('score', 10)->jumpTo('recommend')],
        'company_size' => [Logic::always()->add('score', 100)],
        'recommend' => [
            Logic::whenVariable('score', '>=', 10)->endWith('qualified'),
            Logic::always()->add('score', 1000),
        ],
    ];

    expect(resolvePath(['plan' => 'enterprise'], $rules))->toMatchObject([
        'steps' => ['name', 'plan', 'recommend'],
        'variables' => ['score' => 11],
        'ending' => 'qualified',
    ])->and(resolvePath(['plan' => 'starter'], $rules))->toMatchObject([
        'steps' => ['name', 'plan', 'company_size', 'recommend'],
        'variables' => ['score' => 101],
        'ending' => 'qualified',
    ]);
});

it('starts variables from the given values', function () {
    $rules = ['name' => [Logic::always()->subtract('lives', 1)]];

    expect(resolvePath([], $rules, variables: ['lives' => 3])->variables)->toBe(['lives' => 2]);
});

it('stops at the first matching rule that jumps or ends', function () {
    $rules = ['name' => [
        Logic::always()->add('score', 1),
        Logic::when('name', 'is', 'skip')->add('score', 10),
        Logic::always()->add('score', 100)->jumpTo('company_size'),
        Logic::always()->add('score', 1000)->jumpTo('notes'),
    ]];

    expect(resolvePath([], $rules))->toMatchObject([
        'steps' => ['name', 'company_size', 'recommend', 'notes'],
        'variables' => ['score' => 101],
    ]);
});

it('skips steps whose conditions do not match', function () {
    $conditions = ['company_size' => Logic::any([Logic::condition('plan', 'is', 'enterprise')])];

    expect(resolvePath(['plan' => 'starter'], stepConditions: $conditions)->toArray())->toBe(['name', 'plan', 'recommend', 'notes'])
        ->and(resolvePath(['plan' => 'enterprise'], stepConditions: $conditions)->toArray())->toContain('company_size');
});

it('ignores answers to steps that are no longer on the path', function () use ($planRules) {
    $rules = [
        ...$planRules,
        'recommend' => [Logic::when('company_size', 'is', 'large')->end()],
    ];

    expect(resolvePath(['plan' => 'starter', 'company_size' => 'large'], $rules)->toArray())
        ->toBe(['name', 'plan', 'recommend', 'notes']);
});

it('ignores stale backward targets', function () {
    expect(resolvePath([], ['plan' => [Logic::always()->jumpTo('name')]])->toArray())
        ->toBe(['name', 'plan', 'company_size', 'recommend', 'notes']);
});

it('reports progress along the path', function () use ($planRules) {
    $path = resolvePath(['plan' => 'starter'], $planRules, ['name', 'plan', 'company_size', 'recommend']);

    expect($path->toArray())->toBe(['name', 'plan', 'recommend'])
        ->and($path->after('plan'))->toBe('recommend')
        ->and($path->after('recommend'))->toBeNull()
        ->and($path->isLast('recommend'))->toBeTrue()
        ->and($path->progressAfter('name'))->toBe(0.3333)
        ->and($path->progressAfter('recommend'))->toBe(1.0)
        ->and($path->progressAfter('company_size'))->toBe(0.0);
});

it('always terminates and never visits a step twice, whatever the logic', function (int $seed) {
    mt_srand($seed);

    $steps = array_map(fn (int $i): string => "q{$i}", range(1, mt_rand(2, 25)));
    $rules = [];
    $answers = [];

    foreach ($steps as $index => $step) {
        $answers[$step] = mt_rand(0, 3);

        foreach (range(1, mt_rand(0, 3)) as $ignored) {
            $later = array_slice($steps, $index + 1);
            $rule = Logic::when($step, 'is', mt_rand(0, 3));

            $rules[$step][] = match (true) {
                ($later === []) || (mt_rand(0, 4) === 0) => $rule->end(),
                default => $rule->add('score', 1)->jumpTo($later[array_rand($later)]),
            };
        }
    }

    $path = resolvePath($answers, $rules, $steps);

    expect($path->steps)->toBe(array_values(array_unique($path->steps)))
        ->and(array_values(array_intersect($steps, $path->steps)))->toBe($path->steps)
        ->and($path->first())->toBe('q1');
})->with(range(1, 200));
