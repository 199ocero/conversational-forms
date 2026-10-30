<?php

use JaOcero\ConversationalForms\Builder\DefinitionState;
use JaOcero\ConversationalForms\Builder\LogicMap;
use JaOcero\ConversationalForms\Builder\RuleSummary;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Tests\Fixtures\KitchenSink;

describe('DefinitionState', function () {
    it('round-trips every question type, screen and rule through the builder', function () {
        $definition = ConversationDefinition::fromConversation(conversationIn(conversation(KitchenSink::conversation())));

        $state = DefinitionState::fromDefinition($definition);

        expect(canonical(DefinitionState::toDefinition($state)))->toBe(canonical($definition));
    });

    it('fills in blanks the way people leave them in the builder', function () {
        $definition = DefinitionState::toDefinition([
            'steps' => [
                ['type' => 'multiple_choice', 'data' => [
                    'key' => 'plan', 'label' => 'Plan?', 'required' => true,
                    'settings' => ['options' => ['a' => ['label' => 'Pro Plan', 'value' => ''], 'b' => ['label' => '  '], 'c' => ['label' => 'Team', 'value' => 'team']], 'max_selections' => '2', 'other' => false],
                    'media' => ['url' => null, 'alt' => null],
                    'correct' => ['enabled' => true, 'answer' => 'team', 'points' => '', 'variable' => ''],
                    'logic' => [
                        ['match' => 'all', 'conditions' => [['field' => 'plan', 'operator' => 'is', 'value' => 'team']], 'action' => 'jump_to', 'target' => 'size', 'calculations' => []],
                        ['match' => 'always', 'conditions' => [], 'action' => 'jump_to', 'target' => null, 'calculations' => []],
                    ],
                ]],
                ['type' => 'number', 'data' => ['key' => 'size', 'label' => 'Size?', 'settings' => ['min' => '1', 'max' => '2.5'], 'logic' => [
                    ['match' => 'any', 'conditions' => [
                        ['field' => 'size', 'operator' => 'between', 'values' => ['10', '20']],
                        ['field' => 'size', 'operator' => 'is_empty', 'value' => 'ignored'],
                    ], 'action' => 'none', 'calculations' => [['operation' => 'add', 'variable' => '@score', 'value' => '5']]],
                ]]],
                ['type' => 'yes_no', 'data' => ['key' => 'ok', 'label' => 'OK?', 'visibility' => ['match' => 'all', 'conditions' => [['field' => 'size', 'operator' => 'gt', 'value' => '3']]]]],
            ],
            'variables' => ['score' => '0', 'tier' => 'gold', 'vip' => 'false'],
            'welcome' => ['enabled' => false, 'title' => 'Ignored'],
            'endings' => [['key' => 'default', 'type' => 'end_screen', 'title' => 'Thanks', 'url' => 'https://ignored.example']],
            'settings' => ['progress_bar' => true, 'navigation_buttons' => false, 'auto_advance' => true, 'auto_advance_delay' => ''],
        ]);

        expect($definition['steps'][0]['settings'])->toBe([
            'options' => [['value' => 'pro_plan', 'label' => 'Pro Plan'], ['value' => 'team', 'label' => 'Team']],
            'max_selections' => 2,
            'other' => false,
        ])
            ->and($definition['steps'][0]['correct'])->toBe(['answer' => 'team', 'points' => 1, 'variable' => 'score'])
            // A jump without a step is left out rather than saved half-finished.
            ->and($definition['steps'][0]['rules'])->toHaveCount(1)
            ->and($definition['steps'][1]['settings'])->toBe(['min' => 1, 'max' => 2.5])
            ->and($definition['steps'][1]['rules'][0])->toBe([
                'if' => ['any' => [
                    ['field' => 'size', 'op' => 'between', 'value' => [10, 20]],
                    ['field' => 'size', 'op' => 'is_empty'],
                ]],
                'then' => [['add' => ['score', 5]]],
            ])
            ->and($definition['steps'][2]['visible_when'])->toBe(['all' => [['field' => 'size', 'op' => 'gt', 'value' => 3]]])
            ->and($definition['variables'])->toBe(['score' => 0, 'tier' => 'gold', 'vip' => false])
            ->and($definition)->not->toHaveKey('welcome')
            ->and($definition['endings'])->toBe([['key' => 'default', 'type' => 'end_screen', 'title' => 'Thanks']])
            ->and($definition['settings'])->toBe(['progress_bar' => true, 'navigation_buttons' => false, 'auto_advance' => true]);

        ConversationDefinition::make($definition)->validate();
    });

    it('stores yes / no conditions as booleans', function () {
        $definition = DefinitionState::toDefinition(['steps' => [
            ['type' => 'yes_no', 'data' => ['key' => 'team', 'label' => 'Team?', 'logic' => [
                ['match' => 'all', 'conditions' => [['field' => 'team', 'operator' => 'is', 'value' => '0']], 'action' => 'end'],
            ]]],
        ]]);

        expect($definition['steps'][0]['rules'][0]['if'])->toBe(['all' => [['field' => 'team', 'op' => 'is', 'value' => false]]]);
    });

    it('keeps rules with nested groups as they are', function () {
        $rule = ['if' => ['any' => [['all' => [['field' => 'a', 'op' => 'is', 'value' => 1]]], ['field' => 'b', 'op' => 'is_empty', 'value' => null]]], 'then' => [['end' => true]]];
        $definition = ['steps' => [['type' => 'short_text', 'key' => 'a', 'rules' => [$rule]], ['type' => 'short_text', 'key' => 'b']]];

        $state = DefinitionState::fromDefinition($definition);
        $row = array_values(array_values($state['steps'])[0]['data']['logic'])[0];

        expect($row['raw'])->not->toBeNull()
            ->and(RuleSummary::describe($row))->toBe('Rule written in code')
            ->and(DefinitionState::toDefinition($state)['steps'][0]['rules'])->toBe([$rule]);
    });

    it('makes keys from labels', function (?string $label, string $key) {
        expect(DefinitionState::keyFromLabel($label))->toBe($key);
    })->with([
        ["What's your name?", 'whats_your_name'],
        ['Nice to meet you, {name}. Email?', 'nice_to_meet_you_email'],
        ['2024 budget', 'question_2024_budget'],
        ['', 'question'],
        [null, 'question'],
        ['Ça va, Zoë?', 'ca_va_zoe'],
    ]);

    it('describes rules in a sentence', function () {
        expect(RuleSummary::describe([
            'match' => 'all',
            'conditions' => [['field' => 'plan', 'operator' => 'is', 'value' => 'pro'], ['field' => 'seats', 'operator' => 'between', 'values' => [5, 9]]],
            'action' => 'jump_to', 'target' => 'company',
            'calculations' => [['operation' => 'add', 'variable' => 'score', 'value' => 2]],
        ], ['company' => 'company']))->toBe('When plan is pro and seats is between 5, 9 → add 2 → @score, jump to company')
            ->and(RuleSummary::describe(['match' => 'always', 'action' => 'end']))->toBe('Always → end');
    });
});

describe('LogicMap', function () {
    it('maps the default flow, jumps and endings', function () {
        $map = LogicMap::fromDefinition([
            'steps' => [
                ['type' => 'multiple_choice', 'key' => 'plan', 'label' => 'Plan?', 'rules' => [
                    ['if' => ['all' => [['field' => 'plan', 'op' => 'is', 'value' => 'pro']]], 'then' => [['jump_to' => 'size']]],
                    ['if' => ['all' => [['field' => 'plan', 'op' => 'is', 'value' => 'none']]], 'then' => [['end_with' => 'bye']]],
                ]],
                ['type' => 'statement', 'key' => 'intro', 'title' => 'Hello', 'visible_when' => ['all' => [['field' => 'plan', 'op' => 'is_not_empty']]]],
                ['type' => 'number', 'key' => 'size', 'label' => 'Size?'],
            ],
            'endings' => [['key' => 'default'], ['key' => 'bye', 'title' => 'Bye']],
        ]);

        expect(array_column($map->nodes, 'id'))->toBe(['step:plan', 'step:intro', 'step:size', 'ending:default', 'ending:bye'])
            ->and(array_column($map->nodes, 'number'))->toBe([1, null, 2, null, null])
            ->and($map->nodes[1]['conditional'])->toBeTrue()
            ->and($map->edges)->toBe([
                ['from' => 'step:plan', 'to' => 'step:size', 'kind' => 'jump', 'label' => 'plan is pro'],
                ['from' => 'step:plan', 'to' => 'ending:bye', 'kind' => 'end', 'label' => 'plan is none'],
                ['from' => 'step:plan', 'to' => 'step:intro', 'kind' => 'next', 'label' => ''],
                ['from' => 'step:intro', 'to' => 'step:size', 'kind' => 'next', 'label' => ''],
                ['from' => 'step:size', 'to' => 'ending:default', 'kind' => 'next', 'label' => ''],
            ])
            ->and($map->warnings)->toBe([]);
    });

    it('warns about steps and endings that can never be reached', function () {
        $map = LogicMap::fromDefinition([
            'steps' => [
                ['type' => 'short_text', 'key' => 'a', 'rules' => [['if' => 'always', 'then' => [['add' => ['score', 1]], ['jump_to' => 'c']]]]],
                ['type' => 'short_text', 'key' => 'b'],
                ['type' => 'short_text', 'key' => 'c'],
            ],
            'endings' => [['key' => 'default'], ['key' => 'unused']],
        ]);

        expect($map->warnings)->toBe([
            'The step "b" can never be reached: an earlier rule always jumps past it.',
            'The ending "unused" is never used.',
        ])
            ->and(collect($map->nodes)->firstWhere('key', 'b')['reachable'])->toBeFalse();
    });
});
