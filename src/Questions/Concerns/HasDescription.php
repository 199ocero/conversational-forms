<?php

namespace JaOcero\ConversationalForms\Questions\Concerns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;

trait HasDescription
{
    protected string | Htmlable | Closure | null $description = null;

    /**
     * Supporting text shown under the question.
     */
    public function description(string | Htmlable | Closure | null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): string | Htmlable | null
    {
        return $this->evaluate($this->description);
    }
}
