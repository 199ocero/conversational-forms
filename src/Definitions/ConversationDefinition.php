<?php

namespace JaOcero\ConversationalForms\Definitions;

use Filament\Forms\Components\Field;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Components\QuestionGroup;
use JaOcero\ConversationalForms\Components\Statement;
use JaOcero\ConversationalForms\Components\Step;
use JaOcero\ConversationalForms\Contracts\ConversationQuestion;
use JaOcero\ConversationalForms\Definitions\Exceptions\InvalidDefinitionException;
use JaOcero\ConversationalForms\Logic\Condition;
use JaOcero\ConversationalForms\Logic\ConditionGroup;
use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JaOcero\ConversationalForms\Logic\LogicGraph;
use JaOcero\ConversationalForms\Logic\Rule;
use JaOcero\ConversationalForms\Questions\HiddenField;
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\Redirect;
use JaOcero\ConversationalForms\Screens\WelcomeScreen;

/**
 * A conversation as plain data, the format the builder saves:
 *
 *     [
 *         'schema_version' => 1,
 *         'settings' => ['progress_bar' => true, 'auto_advance' => true],
 *         'variables' => ['score' => 0],
 *         'welcome' => ['title' => 'Hi!', 'button_label' => 'Start'],
 *         'hidden_fields' => [['key' => 'utm_source', 'from_query' => 'utm_source']],
 *         'steps' => [
 *             ['type' => 'short_text', 'key' => 'name', 'label' => "What's your name?", 'required' => true],
 *             ['type' => 'multiple_choice', 'key' => 'plan', 'settings' => ['options' => [...]], 'rules' => [...]],
 *             ['type' => 'statement', 'key' => 'intro', 'title' => '...'],
 *             ['type' => 'group', 'key' => 'company', 'questions' => [...]],
 *         ],
 *         'endings' => [['key' => 'default', 'type' => 'end_screen', 'title' => 'Thanks, {name}!']],
 *     ]
 *
 * Rules are stored on the step they follow (`rules`, in `Rule::toArray()`
 * form). `make()` builds the same `Conversation` that code would, so a stored
 * conversation and a code-defined one behave identically.
 */
final class ConversationDefinition
{
    public const SCHEMA_VERSION = 1;

    public const KEY_PATTERN = '/^[A-Za-z]\w*$/';

    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(private array $definition) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function make(array $definition): self
    {
        return new self($definition);
    }

    /**
     * The definition of a conversation defined in code. The conversation must
     * belong to a schema, since its steps are only known then.
     *
     * @return array<string, mixed>
     */
    public static function fromConversation(Conversation $conversation): array
    {
        $registry = self::registry();
        $conversationLogic = $conversation->getConversationLogic();

        $steps = array_map(function (Step $step) use ($registry, $conversationLogic): array {
            $definition = match (true) {
                $step instanceof Statement => $step->toDefinition(),
                $step instanceof QuestionGroup => [
                    ...$step->toDefinition(),
                    'questions' => array_map(
                        fn (Field $question): array => self::questionToDefinition($question, $registry),
                        $step->getFields(),
                    ),
                ],
                default => self::questionToDefinition($step->getFields()[0], $registry),
            };

            $ownRules = [];

            foreach ($step->getFields() as $field) {
                if ($field instanceof ConversationQuestion) {
                    $ownRules = [...$ownRules, ...$field->getOwnLogic()];
                }
            }

            $rules = [...$ownRules, ...($conversationLogic[$step->getName()] ?? [])];

            if ($rules !== []) {
                $definition['rules'] = array_map(fn (Rule $rule): array => $rule->toArray(), $rules);
            }

            return $definition;
        }, array_values($conversation->getSteps()));

        $welcome = $conversation->getWelcomeScreen();

        return array_filter([
            'schema_version' => self::SCHEMA_VERSION,
            'settings' => [
                'progress_bar' => $conversation->hasProgressBar(),
                'navigation_buttons' => $conversation->hasNavigationButtons(),
                'auto_advance' => $conversation->isAutoAdvanceEnabled(),
                'auto_advance_delay' => $conversation->getAutoAdvanceDelay(),
            ],
            'variables' => $conversation->getInitialVariables() ?: null,
            'welcome' => $welcome?->toDefinition(),
            'hidden_fields' => array_map(fn (HiddenField $field): array => $field->toDefinition(), $conversation->getHiddenFields()) ?: null,
            'steps' => $steps,
            'endings' => array_values(array_map(fn (EndScreen $screen): array => $screen->toDefinition(), $conversation->getEndScreens())),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Configure a conversation (a new one by default) from the definition.
     *
     * @throws InvalidDefinitionException
     */
    public function toConversation(?Conversation $conversation = null): Conversation
    {
        $this->ensureSupportedVersion();

        $registry = self::registry();
        $settings = $this->definition['settings'] ?? [];

        $components = [
            ...array_map(
                fn (array $field): HiddenField => HiddenField::make($field['key'])->fillFromDefinition($field),
                $this->definition['hidden_fields'] ?? [],
            ),
            ...array_map(fn (array $step): Field | Step => $this->makeStep($step, $registry), $this->getStepDefinitions()),
        ];

        $conversation ??= Conversation::make();

        $conversation
            ->components($components)
            ->variables($this->definition['variables'] ?? [])
            ->logic($this->getRules())
            ->progressBar((bool) ($settings['progress_bar'] ?? true))
            ->navigationButtons((bool) ($settings['navigation_buttons'] ?? true))
            ->autoAdvance((bool) ($settings['auto_advance'] ?? true), isset($settings['auto_advance_delay']) ? (int) $settings['auto_advance_delay'] : null);

        if (filled($this->definition['welcome'] ?? null)) {
            $conversation->welcomeScreen(WelcomeScreen::make()->fillFromDefinition($this->definition['welcome']));
        }

        $endings = $this->definition['endings'] ?? [];

        if ($endings !== []) {
            $conversation->endScreens(array_map(
                fn (array $ending): EndScreen => ((($ending['type'] ?? 'end_screen') === 'redirect') ? Redirect::make($ending['key'] ?? 'default') : EndScreen::make($ending['key'] ?? 'default'))
                    ->fillFromDefinition($ending),
                $endings,
            ));
        }

        return $conversation;
    }

    /**
     * Check the definition without building a page: known question types,
     * valid and unique keys, and logic that only jumps forward to steps and
     * endings that exist.
     *
     * @throws InvalidDefinitionException
     * @throws InvalidLogicException
     */
    public function validate(): void
    {
        $this->ensureSupportedVersion();

        $registry = self::registry();
        $keys = [];

        $remember = function (string $key) use (&$keys): void {
            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                throw InvalidDefinitionException::invalidKey($key);
            }

            if (isset($keys[$key])) {
                throw InvalidDefinitionException::duplicateKey($key);
            }

            $keys[$key] = true;
        };

        foreach ($this->definition['hidden_fields'] ?? [] as $field) {
            $remember((string) ($field['key'] ?? ''));
        }

        foreach ($this->getStepDefinitions() as $step) {
            $remember((string) ($step['key'] ?? ''));

            $type = $step['type'] ?? '';

            if ($type === 'group') {
                foreach ($step['questions'] ?? [] as $question) {
                    $remember((string) ($question['key'] ?? ''));
                    $registry->get((string) ($question['type'] ?? ''));
                }
            } elseif ($type !== 'statement') {
                $registry->get((string) $type);
            }
        }

        $endings = array_map(fn (array $ending): string => (string) ($ending['key'] ?? 'default'), $this->definition['endings'] ?? []);

        foreach (array_count_values($endings) as $ending => $count) {
            if ($count > 1) {
                throw InvalidDefinitionException::duplicateKey((string) $ending);
            }
        }

        $rules = $this->getRules();

        foreach ($this->getStepDefinitions() as $step) {
            $conditions = [
                ...array_map(fn (Rule $rule): ?ConditionGroup => $rule->getConditions(), $rules[$step['key']] ?? []),
                isset($step['visible_when']) ? ConditionGroup::fromArray($step['visible_when']) : null,
                ...array_map(
                    fn (array $question): ?ConditionGroup => isset($question['visible_when']) ? ConditionGroup::fromArray($question['visible_when']) : null,
                    $step['questions'] ?? [],
                ),
            ];

            foreach (array_filter($conditions) as $group) {
                foreach (self::conditionFields($group) as $field) {
                    if ((! str_starts_with($field, '@')) && (! isset($keys[strtok($field, '.')]))) {
                        throw InvalidDefinitionException::unknownField((string) $step['key'], $field);
                    }
                }
            }
        }

        (new LogicGraph)->validate($this->getStepKeys(), $rules, $endings === [] ? ['default'] : $endings);
    }

    /**
     * Every question, including those in groups, keyed by question key in
     * the order they're asked. They aren't part of a form, so they can be
     * used to describe answers: `$questions['plan']->formatAnswer('pro')`.
     *
     * @return array<string, Field&ConversationQuestion>
     */
    public function getQuestions(): array
    {
        $registry = self::registry();
        $questions = [];

        foreach ($this->getStepDefinitions() as $step) {
            $type = $step['type'] ?? null;

            if ($type === 'statement') {
                continue;
            }

            foreach (($type === 'group') ? ($step['questions'] ?? []) : [$step] as $definition) {
                $type = (string) ($definition['type'] ?? '');

                if (! $registry->has($type)) {
                    continue;
                }

                $question = $registry->get($type)->makeQuestion((string) $definition['key']);
                $question->fillFromDefinition($definition);
                $questions[(string) $definition['key']] = $question;
            }
        }

        return $questions;
    }

    /**
     * @return list<string>
     */
    public function getHiddenFieldKeys(): array
    {
        return array_map(fn (array $field): string => (string) $field['key'], $this->definition['hidden_fields'] ?? []);
    }

    /**
     * @return list<string>
     */
    public function getStepKeys(): array
    {
        return array_map(fn (array $step): string => (string) $step['key'], $this->getStepDefinitions());
    }

    /**
     * @return list<string>
     */
    public function getEndingKeys(): array
    {
        $endings = array_map(fn (array $ending): string => (string) ($ending['key'] ?? 'default'), $this->definition['endings'] ?? []);

        return ($endings === []) ? ['default'] : $endings;
    }

    /**
     * @return array<string, list<Rule>>
     */
    public function getRules(): array
    {
        $rules = [];

        foreach ($this->getStepDefinitions() as $step) {
            if (filled($step['rules'] ?? null)) {
                $rules[(string) $step['key']] = array_map(Rule::fromArray(...), array_values($step['rules']));
            }
        }

        return $rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getStepDefinitions(): array
    {
        return array_values($this->definition['steps'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['schema_version' => self::SCHEMA_VERSION, ...$this->definition];
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function makeStep(array $step, QuestionTypeRegistry $registry): Field | Step
    {
        return match ($step['type'] ?? null) {
            'statement' => Statement::make($step['key'])->fillFromDefinition($step),
            'group' => QuestionGroup::make($step['key'], array_map(
                fn (array $question): Field => $this->makeQuestion($question, $registry),
                $step['questions'] ?? [],
            ))->fillFromDefinition($step),
            default => $this->makeQuestion($step, $registry),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function makeQuestion(array $definition, QuestionTypeRegistry $registry): Field
    {
        return $registry->get((string) ($definition['type'] ?? ''))
            ->makeQuestion((string) $definition['key'])
            ->fillFromDefinition($definition);
    }

    /**
     * @return array<string, mixed>
     */
    private static function questionToDefinition(Field $question, QuestionTypeRegistry $registry): array
    {
        if (! $question instanceof ConversationQuestion) {
            throw InvalidDefinitionException::unregisteredQuestion($question::class);
        }

        return ['type' => $registry->forQuestion($question)->getName(), ...$question->toDefinition()];
    }

    /**
     * @return list<string>
     */
    private static function conditionFields(ConditionGroup $group): array
    {
        $fields = [];

        foreach ($group->getItems() as $item) {
            $fields = [...$fields, ...(($item instanceof Condition) ? [$item->field] : self::conditionFields($item))];
        }

        return $fields;
    }

    private function ensureSupportedVersion(): void
    {
        $version = (int) ($this->definition['schema_version'] ?? self::SCHEMA_VERSION);

        if ($version > self::SCHEMA_VERSION) {
            throw InvalidDefinitionException::unsupportedVersion($version);
        }
    }

    private static function registry(): QuestionTypeRegistry
    {
        return app(QuestionTypeRegistry::class);
    }
}
