<?php

namespace JaOcero\ConversationalForms\Questions\Concerns;

use Closure;
use JaOcero\ConversationalForms\Logic\Rule;

trait HasLogic
{
    /**
     * @var array<Rule> | Closure
     */
    protected array | Closure $logic = [];

    /**
     * Rules evaluated after this question is answered, in order. The first rule
     * whose conditions match decides where the conversation goes next.
     *
     * @param  array<Rule> | Closure  $rules
     */
    public function logic(array | Closure $rules): static
    {
        $this->logic = $rules;

        return $this;
    }

    /**
     * @return list<Rule>
     */
    public function getLogic(): array
    {
        return [...$this->getScoringRules(), ...array_values($this->evaluate($this->logic))];
    }

    /**
     * Rules generated from shorthand such as `correct()` and `points()`. They
     * run before the question's own rules.
     *
     * @return list<Rule>
     */
    protected function getScoringRules(): array
    {
        return [];
    }
}
