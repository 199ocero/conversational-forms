<?php

namespace JaOcero\ConversationalForms\Screens;

use Closure;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A screen shown outside the question flow, such as the welcome or end screen.
 */
abstract class Screen extends Component
{
    protected string | Htmlable | Closure | null $title = null;

    protected string | Htmlable | Closure | null $description = null;

    protected string | Closure | null $buttonLabel = null;

    final public function __construct() {}

    public static function make(): static
    {
        $static = app(static::class);
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

    abstract public function getButtonLabel(): ?string;
}
