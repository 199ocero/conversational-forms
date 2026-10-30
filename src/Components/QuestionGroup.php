<?php

namespace JaOcero\ConversationalForms\Components;

use Closure;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use JaOcero\ConversationalForms\Concerns\HasVisibilityConditions;
use JaOcero\ConversationalForms\Logic\ConditionGroup;

/**
 * Several questions on one screen, validated together, for example a first
 * and last name. Logic attached to any question in the group applies after
 * the whole group is answered.
 */
class QuestionGroup extends Step
{
    use HasVisibilityConditions;

    protected string $view = 'conversational-forms::schemas.question-group';

    protected string | Htmlable | Closure | null $label = null;

    protected string | Htmlable | Closure | null $description = null;

    /**
     * @param  array<Component>  $components
     */
    final public function __construct(string $name, array $components = [])
    {
        $this->name = $name;
        $this->schema($components);
    }

    /**
     * @param  array<Component>  $components
     */
    public static function make(string $name, array $components = []): static
    {
        $static = app(static::class, ['name' => $name, 'components' => $components]);
        $static->configure();

        return $static;
    }

    public function label(string | Htmlable | Closure | null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function description(string | Htmlable | Closure | null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLabel(): string | Htmlable | null
    {
        return $this->evaluate($this->label);
    }

    public function getDescription(): string | Htmlable | null
    {
        return $this->evaluate($this->description);
    }

    public function isNumbered(): bool
    {
        return true;
    }

    /**
     * The group without its questions; `ConversationDefinition` adds them.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return array_filter([
            'type' => 'group',
            'key' => $this->getName(),
            'label' => static::definitionText($this->evaluate($this->label)),
            'description' => static::definitionText($this->evaluate($this->description)),
            'visible_when' => $this->getVisibilityConditions()?->toArray(),
        ], filled(...));
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function fillFromDefinition(array $definition): static
    {
        $this->label($definition['label'] ?? null);
        $this->description($definition['description'] ?? null);

        if (filled($definition['visible_when'] ?? null)) {
            $this->visibleWhen(ConditionGroup::fromArray($definition['visible_when']));
        }

        return $this;
    }
}
