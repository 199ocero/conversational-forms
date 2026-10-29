<?php

use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Logic\Rule;

it('matches when every condition matches', function () {
    $rule = Logic::when('plan', 'is', 'enterprise')->and('seats', '!=', 1)->jumpTo('company_size');

    expect($rule->matches(['plan' => 'enterprise', 'seats' => '20']))->toBeTrue()
        ->and($rule->matches(['plan' => 'enterprise', 'seats' => '1']))->toBeFalse()
        ->and($rule->matches(['plan' => 'starter', 'seats' => '20']))->toBeFalse();
});

it('reads nested answers with dot notation', function () {
    expect(Logic::when('company.size', 'is', 'large')->matches(['company' => ['size' => 'large']]))->toBeTrue();
});

it('always matches without conditions', function () {
    expect(Logic::always()->jumpTo('feedback')->matches([]))->toBeTrue()
        ->and(Logic::always()->isAlways())->toBeTrue();
});

it('serializes to plain data', function () {
    expect(Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size')->toArray())->toBe([
        'if' => ['all' => [['field' => 'plan', 'op' => 'is', 'value' => 'enterprise']]],
        'then' => ['jump_to' => 'company_size'],
    ])->and(Logic::always()->end()->toArray())->toBe([
        'if' => 'always',
        'then' => ['end' => true],
    ])->and(json_encode(Logic::always()->jumpTo('x')))->toBe('{"if":"always","then":{"jump_to":"x"}}');
});

it('marks rules that end the conversation', function () {
    expect(Logic::when('age', 'is', 'under_18')->end()->endsConversation())->toBeTrue()
        ->and(Logic::when('age', 'is', 'under_18')->end()->getTarget())->toBe(Rule::END);
});
