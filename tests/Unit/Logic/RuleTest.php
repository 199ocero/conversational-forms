<?php

use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JaOcero\ConversationalForms\Logic\Logic;

it('matches when every condition matches', function () {
    $rule = Logic::when('plan', 'is', 'enterprise')->and('seats', '>=', 20)->jumpTo('sales');

    expect($rule->matches(['plan' => 'enterprise', 'seats' => '25']))->toBeTrue()
        ->and($rule->matches(['plan' => 'enterprise', 'seats' => '5']))->toBeFalse()
        ->and($rule->matches(['plan' => 'starter', 'seats' => '25']))->toBeFalse();
});

it('matches when any condition matches', function () {
    $rule = Logic::when('plan', 'is', 'enterprise')->or('seats', '>', 100)->jumpTo('sales');

    expect($rule->matches(['plan' => 'starter', 'seats' => 200]))->toBeTrue()
        ->and($rule->matches(['plan' => 'starter', 'seats' => 5]))->toBeFalse();
});

it('supports nested groups', function () {
    $rule = Logic::when(Logic::all([
        Logic::condition('country', 'is', 'ph'),
        Logic::any([
            Logic::condition('age', '<', 18),
            Logic::condition('student', 'is', true),
        ]),
    ]))->jumpTo('discount');

    expect($rule->matches(['country' => 'ph', 'age' => 30, 'student' => true]))->toBeTrue()
        ->and($rule->matches(['country' => 'ph', 'age' => 30, 'student' => false]))->toBeFalse()
        ->and($rule->matches(['country' => 'us', 'age' => 12]))->toBeFalse();
});

it('refuses to mix and() and or() in one chain', function () {
    Logic::when('a', 'is', 1)->and('b', 'is', 2)->or('c', 'is', 3);
})->throws(InvalidLogicException::class, 'A rule cannot mix [and()] and [or()].');

it('reads variables with whenVariable() or an @ prefix', function () {
    expect(Logic::whenVariable('score', '>=', 70)->endWith('passed')->matches([], ['score' => 80]))->toBeTrue()
        ->and(Logic::when('@score', '<', 70)->endWith('failed')->matches(['score' => 10], ['score' => 80]))->toBeFalse();
});

it('runs calculations in order', function () {
    $rule = Logic::when('q1', 'is', 'b')->add('score', 10)->multiply('score', 2)->subtract('score', 5)->set('passed_q1', true);

    expect($rule->applyCalculations(['score' => 1]))->toBe(['score' => 17, 'passed_q1' => true])
        ->and($rule->applyCalculations([]))->toBe(['score' => 15, 'passed_q1' => true])
        ->and(Logic::always()->divide('score', 0)->applyCalculations(['score' => 9]))->toBe(['score' => 9]);
});

it('always matches without conditions', function () {
    expect(Logic::always()->jumpTo('feedback')->matches([]))->toBeTrue()
        ->and(Logic::always()->isAlways())->toBeTrue();
});

it('distinguishes routing from calculating', function () {
    expect(Logic::always()->add('score', 1)->routes())->toBeFalse()
        ->and(Logic::always()->add('score', 1)->hasAction())->toBeTrue()
        ->and(Logic::when('a', 'is', 1)->hasAction())->toBeFalse()
        ->and(Logic::always()->end()->endsConversation())->toBeTrue()
        ->and(Logic::always()->end()->getEnding())->toBeNull()
        ->and(Logic::always()->endWith('failed')->getEnding())->toBe('failed');
});

it('serializes to plain data', function () {
    expect(Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size')->toArray())->toBe([
        'if' => ['all' => [['field' => 'plan', 'op' => 'is', 'value' => 'enterprise']]],
        'then' => [['jump_to' => 'company_size']],
    ])->and(Logic::always()->end()->toArray())->toBe([
        'if' => 'always',
        'then' => [['end' => true]],
    ])->and(Logic::when('q1', 'is', 'b')->add('score', 10)->endWith('passed')->toArray())->toBe([
        'if' => ['all' => [['field' => 'q1', 'op' => 'is', 'value' => 'b']]],
        'then' => [['add' => ['score', 10]], ['end_with' => 'passed']],
    ])->and(json_encode(Logic::when('a', 'gt', 1)->or('b', 'is', true)->jumpTo('x')))
        ->toBe('{"if":{"any":[{"field":"a","op":"gt","value":1},{"field":"b","op":"is","value":true}]},"then":[{"jump_to":"x"}]}');
});
