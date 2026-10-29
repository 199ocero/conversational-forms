<?php

use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Logic\LogicGraph;

$steps = ['name', 'plan', 'company_size', 'recommend'];

it('accepts forward jumps and early endings', function () use ($steps) {
    (new LogicGraph)->validate($steps, [
        'plan' => [Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'), Logic::always()->jumpTo('recommend')],
        'company_size' => [Logic::when('company_size', 'is', '1')->end(), Logic::when('company_size', 'is', '2')->endWith('small')],
        'recommend' => [Logic::always()->add('score', 1)],
    ], ['default', 'small']);
})->throwsNoExceptions();

it('rejects invalid logic with a precise message', function (array $rules, string $message) use ($steps) {
    expect(fn () => (new LogicGraph)->validate($steps, $rules))
        ->toThrow(InvalidLogicException::class, $message);
})->with([
    'backward jump' => [['recommend' => [Logic::always()->jumpTo('plan')]], 'A logic rule on [recommend] jumps to [plan], which does not come after it.'],
    'jump to itself' => [['plan' => [Logic::always()->jumpTo('plan')]], 'A logic rule on [plan] jumps to [plan], which does not come after it.'],
    'unknown target' => [['plan' => [Logic::always()->jumpTo('pricing')]], 'A logic rule on [plan] jumps to [pricing], but no step with that name exists'],
    'unknown step' => [['pricing' => [Logic::always()->jumpTo('recommend')]], 'Logic is attached to [pricing], but no step with that name exists'],
    'missing action' => [['plan' => [Logic::when('plan', 'is', 'enterprise')]], 'A logic rule on [plan] has no action.'],
    'unknown ending' => [['plan' => [Logic::always()->endWith('vip')]], 'A logic rule on [plan] ends with [vip], but no end screen with that name exists'],
]);
