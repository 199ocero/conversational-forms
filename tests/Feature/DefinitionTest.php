<?php

use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Definitions\Exceptions\InvalidDefinitionException;
use JaOcero\ConversationalForms\Definitions\QuestionType;
use JaOcero\ConversationalForms\Definitions\QuestionTypeRegistry;
use JaOcero\ConversationalForms\Logic\Enums\AnswerType;
use JaOcero\ConversationalForms\Logic\Enums\Operator;
use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Logic\Rule;
use JaOcero\ConversationalForms\Questions;
use JaOcero\ConversationalForms\Tests\Fixtures\KitchenSink;

describe('ConversationDefinition', function () {
    it('saves every question type, screen and rule, and builds the same definition back', function () {
        $definition = ConversationDefinition::fromConversation(conversationIn(conversation(KitchenSink::conversation())));

        expect($definition['schema_version'])->toBe(1)
            ->and(array_column($definition['steps'], 'type'))->toBe([
                'short_text', 'long_text', 'email', 'website', 'phone', 'number', 'multiple_choice', 'multiple_choice', 'group', 'statement',
                'picture_choice', 'dropdown', 'legal_consent', 'checkbox', 'rating', 'opinion_scale', 'nps', 'date', 'time',
                'contact_info', 'address', 'matrix', 'ranking', 'file_upload', 'signature',
            ])
            ->and($definition['hidden_fields'])->toBe([['key' => 'utm_source', 'from_query' => 'utm_source', 'max_length' => 100]])
            ->and($definition['settings'])->toBe(['progress_bar' => false, 'navigation_buttons' => true, 'auto_advance' => true, 'auto_advance_delay' => 250])
            ->and(array_column($definition['endings'], 'key'))->toBe(['default', 'priority', 'sorry']);

        $rebuilt = ConversationDefinition::fromConversation(conversationIn(definitionConversation($definition)));

        expect($rebuilt)->toBe($definition);
    });

    it('stores each step\'s rules on the step, after the question\'s own', function () {
        $steps = collect(ConversationDefinition::fromConversation(conversationIn(conversation(KitchenSink::conversation()))))->get('steps');
        $steps = collect($steps)->keyBy('key');

        expect($steps['plan']['rules'])->toBe([
            ['if' => ['all' => [['field' => 'plan', 'op' => 'is', 'value' => 'enterprise']]], 'then' => [['jump_to' => 'company']]],
            ['if' => 'always', 'then' => [['jump_to' => 'about_you']]],
        ])
            // `correct()` is kept as quiz settings, not as a generated rule.
            ->and($steps['plan']['correct'])->toBe(['answer' => 'enterprise', 'points' => 5, 'variable' => 'score'])
            ->and($steps['seats']['rules'][0]['then'])->toBe([['add' => ['lead_score', 30]], ['jump_to' => 'plan']])
            ->and($steps['consent']['rules'][0]['if'])->toBe(['all' => [['field' => '@lead_score', 'op' => 'gte', 'value' => 30]]]);
    });

    it('follows the same paths as the conversation defined in code', function (array $answers) {
        $code = conversation(KitchenSink::conversation());
        $definition = ConversationDefinition::fromConversation(conversationIn($code));
        $stored = definitionConversation($definition);

        foreach ($answers as $field => $value) {
            $code->set("data.{$field}", $value);
            $stored->set("data.{$field}", $value);
        }

        $expected = conversationIn($code)->resolvePath();
        $actual = conversationIn($stored)->resolvePath();

        expect($actual->steps)->toBe($expected->steps)
            ->and($actual->ending)->toBe($expected->ending)
            ->and($actual->variables)->toBe($expected->variables);
    })->with([
        'no answers' => [[]],
        'a big enterprise' => [['seats' => 150, 'plan' => 'enterprise', 'consent' => true, 'recommend' => 9]],
        'a starter' => [['seats' => 3, 'plan' => 'starter', 'recommend' => 9]],
        'a detractor' => [['plan' => 'starter', 'recommend' => 2]],
        'an enterprise detractor' => [['seats' => 150, 'plan' => 'enterprise', 'recommend' => 1]],
    ]);

    it('runs a stored conversation like a coded one', function () {
        definitionConversation([
            'steps' => [
                ['type' => 'short_text', 'key' => 'name', 'label' => "What's your name?", 'required' => true],
                ['type' => 'multiple_choice', 'key' => 'plan', 'settings' => ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
                    'rules' => [['if' => ['all' => [['field' => 'plan', 'op' => 'is', 'value' => 'b']]], 'then' => [['end_with' => 'bye']]]]],
                ['type' => 'yes_no', 'key' => 'more'],
            ],
            'endings' => [['key' => 'default', 'title' => 'Thanks!'], ['key' => 'bye', 'title' => 'Bye, {name}']],
        ])
            ->assertSee("What's your name?")
            ->advanceConversation('name')
            ->assertHasErrors(['data.name' => 'required'])
            ->set('data.name', 'Jay')
            ->set('data.plan', 'b')
            ->advanceConversation('name')
            ->assertConversationAdvancedTo('plan')
            ->submitConversation()
            ->assertConversationCompleted('bye');
    });

    it('rejects definitions it can\'t build', function (array $definition, string $exception, string $message) {
        expect(fn () => ConversationDefinition::make($definition)->validate())->toThrow($exception, $message);
    })->with([
        'unknown type' => [['steps' => [['type' => 'hologram', 'key' => 'a']]], InvalidDefinitionException::class, 'no question type called [hologram]'],
        'duplicate key' => [['steps' => [['type' => 'short_text', 'key' => 'a'], ['type' => 'email', 'key' => 'a']]], InvalidDefinitionException::class, 'The key [a] is used more than once'],
        'duplicate key in a group' => [['steps' => [['type' => 'short_text', 'key' => 'a'], ['type' => 'group', 'key' => 'g', 'questions' => [['type' => 'email', 'key' => 'a']]]]], InvalidDefinitionException::class, 'The key [a]'],
        'invalid key' => [['steps' => [['type' => 'short_text', 'key' => 'my name']]], InvalidDefinitionException::class, "[my name] can't be used as a key"],
        'condition on an unknown question' => [['steps' => [['type' => 'short_text', 'key' => 'a', 'rules' => [['if' => ['all' => [['field' => 'nope', 'op' => 'is', 'value' => 1]]], 'then' => [['end' => true]]]]]]], InvalidDefinitionException::class, 'refers to [nope]'],
        'backward jump' => [['steps' => [['type' => 'short_text', 'key' => 'a'], ['type' => 'short_text', 'key' => 'b', 'rules' => [['if' => 'always', 'then' => [['jump_to' => 'a']]]]]]], InvalidLogicException::class, 'b'],
        'unknown ending' => [['steps' => [['type' => 'short_text', 'key' => 'a', 'rules' => [['if' => 'always', 'then' => [['end_with' => 'nowhere']]]]]]], InvalidLogicException::class, 'nowhere'],
        'newer schema' => [['schema_version' => 99, 'steps' => []], InvalidDefinitionException::class, 'newer version'],
    ]);

    it('accepts conditions on variables, hidden fields and parts of answers', function () {
        ConversationDefinition::make([
            'hidden_fields' => [['key' => 'ref']],
            'steps' => [
                ['type' => 'contact_info', 'key' => 'contact', 'rules' => [
                    ['if' => ['any' => [
                        ['field' => '@score', 'op' => 'gt', 'value' => 1],
                        ['field' => 'ref', 'op' => 'is', 'value' => 'ad'],
                        ['field' => 'contact.email', 'op' => 'is_empty'],
                    ]], 'then' => [['end' => true]]],
                ]],
            ],
        ])->validate();
    })->throwsNoExceptions();
});

describe('QuestionTypeRegistry', function () {
    it('registers every built-in question', function () {
        expect(array_keys(app(QuestionTypeRegistry::class)->all()))->toHaveCount(23)
            ->and(app(QuestionTypeRegistry::class)->get('nps')->getQuestionClass())->toBe(Questions\Nps::class)
            ->and(app(QuestionTypeRegistry::class)->get('nps')->getLabel())->toBe('NPS');
    });

    it('finds the type of a question, or of its closest registered parent', function () {
        $registry = app(QuestionTypeRegistry::class);
        $custom = new class('custom') extends Questions\Email {};

        expect($registry->forQuestion(Questions\Email::make('e'))->getName())->toBe('email')
            ->and($registry->forQuestion(Questions\ShortText::make('t'))->getName())->toBe('short_text')
            ->and($registry->forQuestion($custom)->getName())->toBe('email');
    });

    it('builds questions of types registered by other packages', function () {
        app(QuestionTypeRegistry::class)->register(
            QuestionType::make('shout', Questions\ShortText::class)->label('Shout')->answerType(AnswerType::Text),
        );

        definitionConversation(['steps' => [['type' => 'shout', 'key' => 'loud', 'label' => 'Say it loud']]])
            ->assertSee('Say it loud');
    });

    it('offers the operators that fit each kind of answer', function () {
        $registry = app(QuestionTypeRegistry::class);

        expect($registry->get('multiple_choice')->getAnswerType())->toBe(AnswerType::Choice)
            ->and($registry->get('multiple_choice')->getAnswerType(['multiple' => true]))->toBe(AnswerType::Choices)
            ->and(Operator::IncludesAny->appliesTo(AnswerType::Choices))->toBeTrue()
            ->and(Operator::IncludesAny->appliesTo(AnswerType::Choice))->toBeFalse()
            ->and(Operator::Before->appliesTo(AnswerType::Date))->toBeTrue()
            ->and(Operator::forAnswerType(AnswerType::Other))->toBe([Operator::IsEmpty, Operator::IsNotEmpty])
            ->and(Operator::GreaterThan->getLabel())->toBe('is greater than');
    });
});

describe('Rule::fromArray', function () {
    it('reads rules back from their array form', function (Rule $rule) {
        expect(Rule::fromArray($rule->toArray())->toArray())->toBe($rule->toArray());
    })->with([
        'jump' => [Logic::when('a', 'is', 1)->jumpTo('b')],
        'and' => [Logic::when('a', 'is', 1)->and('b', 'contains', 'x')->end()],
        'nested groups' => [Logic::when(Logic::any([Logic::condition('a', 'gt', 1), Logic::all([Logic::condition('b', 'is', true), Logic::condition('@score', 'lt', 3)])]))->endWith('x')],
        'calculations' => [Logic::always()->add('score', 2)->subtract('score', 1)->multiply('score', 3)->divide('score', 2)->set('tier', 'gold')],
    ]);

    it('rejects unknown actions', function () {
        Rule::fromArray(['if' => 'always', 'then' => [['teleport' => ['x']]]]);
    })->throws(InvalidLogicException::class, 'teleport');
});
