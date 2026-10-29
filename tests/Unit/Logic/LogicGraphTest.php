<?php

use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Logic\LogicGraph;

$steps = ['name', 'plan', 'company_size', 'recommend'];

it('accepts forward jumps and early endings', function () use ($steps) {
    (new LogicGraph)->validate($steps, [
        'plan' => [Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'), Logic::always()->jumpTo('recommend')],
        'company_size' => [Logic::when('company_size', 'is', '1')->end()],
    ]);
})->throwsNoExceptions();

it('rejects invalid logic with a precise message', function (array $rules, string $message) use ($steps) {
    expect(fn () => (new LogicGraph)->validate($steps, $rules))
        ->toThrow(InvalidLogicException::class, $message);
})->with([
    'backward jump' => [['recommend' => [Logic::always()->jumpTo('plan')]], 'A logic rule on [recommend] jumps to [plan], which does not come after it.'],
    'jump to itself' => [['plan' => [Logic::always()->jumpTo('plan')]], 'A logic rule on [plan] jumps to [plan], which does not come after it.'],
    'unknown target' => [['plan' => [Logic::always()->jumpTo('pricing')]], 'A logic rule on [plan] jumps to [pricing], but no step with that name exists'],
    'unknown step' => [['pricing' => [Logic::always()->jumpTo('recommend')]], 'Logic is attached to [pricing], but no step with that name exists'],
    'missing action' => [['plan' => [Logic::when('plan', 'is', 'enterprise')]], 'A logic rule on [plan] has conditions but no action.'],
]);
