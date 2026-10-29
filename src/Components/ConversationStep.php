<?php

namespace JaOcero\ConversationalForms\Components;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use JaOcero\ConversationalForms\Logic\Rule;
use JaOcero\ConversationalForms\Questions\Question;
use LogicException;

/**
 * One screen of a conversation. Every child of a `Conversation` is wrapped in a
 * step automatically, which gives each screen its own child schema so that only
 * that screen is validated when the respondent moves forward.
 */
class ConversationStep extends Component
{
    protected string $view = 'conversational-forms::schemas.step';

    /**
     * @param  array<Component>  $components
     */
    final public function __construct(array $components = [], protected ?string $name = null)
    {
        $this->schema($components);
    }

    /**
     * @param  array<Component>  $components
     */
    public static function make(array $components = [], ?string $name = null): static
    {
        $static = app(static::class, ['components' => $components, 'name' => $name]);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->key(fn (ConversationStep $component): string => "cf-step-{$component->getName()}");

        $this->hidden(fn (ConversationStep $component): bool => ! $component->getConversation()->isStepOnPath($component->getName()));
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
            if ($field instanceof Question) {
                $rules = [...$rules, ...$field->getLogic()];
            }
        }

        return $rules;
    }

    public function getQuestion(): ?Question
    {
        foreach ($this->getFields() as $field) {
            if ($field instanceof Question) {
                return $field;
            }
        }

        return null;
    }
}
