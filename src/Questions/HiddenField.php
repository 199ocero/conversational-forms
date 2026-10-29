<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use Filament\Forms\Components\Hidden;

/**
 * A value that is never shown, for example a campaign source passed in the
 * URL (`/apply?utm_source=newsletter`). It is saved with the other answers and
 * can be used in logic and piping.
 *
 * Like any form state, it can be changed by the respondent, so treat it as
 * untrusted input.
 */
class HiddenField extends Hidden
{
    protected string | Closure | null $queryParameter = null;

    protected int | Closure $maxLength = 255;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (HiddenField $component): ?string => $component->getValueFromQuery());

        $this->rule('nullable');
        $this->rule('string');
        $this->rule(fn (HiddenField $component): string => "max:{$component->getMaxLength()}");
    }

    /**
     * Fill the field from a query string parameter when the conversation is
     * opened. Only parameters that are declared this way are read.
     */
    public function fromQuery(string | Closure | null $parameter = null): static
    {
        $this->queryParameter = $parameter ?? fn (HiddenField $component): string => $component->getName();

        return $this;
    }

    public function maxLength(int | Closure $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    public function getQueryParameter(): ?string
    {
        return $this->evaluate($this->queryParameter);
    }

    public function getMaxLength(): int
    {
        return (int) $this->evaluate($this->maxLength);
    }

    public function getValueFromQuery(): ?string
    {
        $parameter = $this->getQueryParameter();

        if (blank($parameter)) {
            return null;
        }

        $value = request()->query($parameter);

        return is_string($value) ? mb_substr($value, 0, $this->getMaxLength()) : null;
    }
}
