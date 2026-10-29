<?php

namespace JaOcero\ConversationalForms\Components;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use JaOcero\ConversationalForms\Concerns\HasVisibilityConditions;

/**
 * A screen without a question, for example an introduction to a new section.
 * It takes part in the flow (and in logic) like any other step.
 */
class Statement extends Step
{
    use HasVisibilityConditions;

    protected string $view = 'conversational-forms::schemas.statement';

    protected string | Htmlable | Closure | null $title = null;

    protected string | Htmlable | Closure | null $description = null;

    protected string | Closure | null $buttonLabel = null;

    final public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    public function title(string | Htmlable | Closure | null $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function description(string | Htmlable | Closure | null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function buttonLabel(string | Closure | null $label): static
    {
        $this->buttonLabel = $label;

        return $this;
    }

    public function getTitle(): string | Htmlable | null
    {
        return $this->evaluate($this->title);
    }

    public function getDescription(): string | Htmlable | null
    {
        return $this->evaluate($this->description);
    }

    public function getContinueLabel(): string
    {
        return $this->evaluate($this->buttonLabel) ?? __('conversational-forms::conversational-forms.navigation.continue');
    }

    public function isNumbered(): bool
    {
        return false;
    }
}
