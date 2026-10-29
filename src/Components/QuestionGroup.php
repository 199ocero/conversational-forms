<?php

namespace JaOcero\ConversationalForms\Components;

use Closure;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Several questions on one screen, validated together, for example a first
 * and last name. Logic attached to any question in the group applies after
 * the whole group is answered.
 */
class QuestionGroup extends Step
{
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
}
