<?php

namespace JaOcero\ConversationalForms\Concerns;

use Closure;
use JaOcero\ConversationalForms\Logic\Condition;
use JaOcero\ConversationalForms\Logic\ConditionGroup;
use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;

trait HasVisibilityConditions
{
    protected Condition | ConditionGroup | Closure | null $visibilityConditions = null;

    /**
     * Only show this when the conditions match, for example
     * `visibleWhen(Logic::condition('employment', 'is', 'employed'))`.
     *
     * The conditions are checked on the server (a hidden question is never
     * required or saved) and in the browser, so the screen updates instantly.
     */
    public function visibleWhen(Condition | ConditionGroup | Closure | null $conditions): static
    {
        $this->visibilityConditions = $conditions;

        return $this;
    }

    public function getVisibilityConditions(): ?ConditionGroup
    {
        $conditions = $this->evaluate($this->visibilityConditions);

        if ($conditions === null) {
            return null;
        }

        if ($conditions instanceof Condition) {
            $conditions = new ConditionGroup(ConditionGroup::ALL, [$conditions]);
        }

        if ($conditions->referencesVariables()) {
            throw InvalidLogicException::variablesInVisibility($this->getName());
        }

        return $conditions;
    }
}
