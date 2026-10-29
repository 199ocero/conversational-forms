<?php

namespace JaOcero\ConversationalForms\Components\Concerns;

use Closure;

trait HasNavigation
{
    protected bool | Closure $hasProgressBar = true;

    protected bool | Closure $hasNavigationButtons = true;

    protected bool | Closure $isAutoAdvanceEnabled = true;

    protected int | Closure $autoAdvanceDelay = 400;

    public function progressBar(bool | Closure $condition = true): static
    {
        $this->hasProgressBar = $condition;

        return $this;
    }

    public function hasProgressBar(): bool
    {
        return (bool) $this->evaluate($this->hasProgressBar);
    }

    /**
     * The previous / next arrow buttons. Keyboard shortcuts work either way.
     */
    public function navigationButtons(bool | Closure $condition = true): static
    {
        $this->hasNavigationButtons = $condition;

        return $this;
    }

    public function hasNavigationButtons(): bool
    {
        return (bool) $this->evaluate($this->hasNavigationButtons);
    }

    /**
     * Move to the next step as soon as a question that supports it is answered,
     * for example after picking a single choice.
     */
    public function autoAdvance(bool | Closure $condition = true, int | Closure | null $delay = null): static
    {
        $this->isAutoAdvanceEnabled = $condition;

        if ($delay !== null) {
            $this->autoAdvanceDelay = $delay;
        }

        return $this;
    }

    public function isAutoAdvanceEnabled(): bool
    {
        return (bool) $this->evaluate($this->isAutoAdvanceEnabled);
    }

    /**
     * Milliseconds to show the selected answer before moving on.
     */
    public function getAutoAdvanceDelay(): int
    {
        return (int) $this->evaluate($this->autoAdvanceDelay);
    }
}
