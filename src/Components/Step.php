<?php

namespace JaOcero\ConversationalForms\Components;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use JaOcero\ConversationalForms\Contracts\ConversationQuestion;
use JaOcero\ConversationalForms\Logic\ConditionGroup;
use JaOcero\ConversationalForms\Logic\Rule;
use LogicException;

/**
 * One screen of a conversation. Each step has its own child schema, so only
 * that screen is validated when the respondent moves forward, and it is shown
 * or skipped as a whole by the conversation's logic.
 */
abstract class Step extends Component
{
    protected ?string $name = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key(fn (Step $component): string => "cf-step-{$component->getName()}");

        $this->hidden(fn (Step $component): bool => ! $component->getConversation()->isStepOnPath($component->getName()));
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Steps are named after their first field unless a name is given, so logic
     * can target them by the same name as the question.
     */
    public function getName(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        foreach ($this->getDefaultChildComponents() as $component) {
            if ($component instanceof Field) {
                return $this->name = $component->getName();
            }
        }

        throw new LogicException('A conversation step without a field must be given a name.');
    }

    public function getConversation(): Conversation
    {
        $conversation = $this->getContainer()->getParentComponent();

        if (! $conversation instanceof Conversation) {
            throw new LogicException('A conversation step must be a direct child of a conversation.');
        }

        return $conversation;
    }

    /**
     * @return array<Field>
     */
    public function getFields(): array
    {
        return array_values($this->getChildSchema()->getFlatFields(withHidden: true));
    }

    /**
     * The state paths of this step's fields, relative to the conversation's schema.
     *
     * @return list<string>
     */
    public function getFieldStatePaths(): array
    {
        $containerStatePath = $this->getConversation()->getContainer()->getStatePath();

        return array_map(
            fn (Field $field): string => (string) str($field->getStatePath())
                ->when(filled($containerStatePath), fn ($statePath) => $statePath->after("{$containerStatePath}.")),
            $this->getFields(),
        );
    }

    /**
     * @return list<Rule>
     */
    public function getLogic(): array
    {
        $rules = [];

        foreach ($this->getFields() as $field) {
            if ($field instanceof ConversationQuestion) {
                $rules = [...$rules, ...$field->getLogic()];
            }
        }

        return $rules;
    }

    /**
     * Whether the step counts towards question numbers ("1 →", "2 →").
     */
    public function isNumbered(): bool
    {
        return $this->getQuestion() instanceof ConversationQuestion;
    }

    /**
     * The label of the step's continue button, or `null` for the default.
     */
    public function getContinueLabel(): ?string
    {
        return null;
    }

    /**
     * A step with a single question is shown only when that question's
     * visibility conditions match. Statements and groups set their own.
     */
    public function getVisibilityConditions(): ?ConditionGroup
    {
        $question = $this->getQuestion();

        return (($question instanceof ConversationQuestion) && (count($this->getFields()) === 1)) ? $question->getVisibilityConditions() : null;
    }

    public function getQuestion(): ?ConversationQuestion
    {
        foreach ($this->getFields() as $field) {
            if ($field instanceof ConversationQuestion) {
                return $field;
            }
        }

        return null;
    }
}
