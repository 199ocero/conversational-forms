<?php

namespace JaOcero\ConversationalForms\Tables\Columns;

use Closure;
use Filament\Tables\Columns\Column;

/**
 * A rating shown as filled and empty stars.
 */
class RatingColumn extends Column
{
    protected string $view = 'conversational-forms::tables.columns.rating';

    protected int | Closure $steps = 5;

    public function steps(int | Closure $steps): static
    {
        $this->steps = $steps;

        return $this;
    }

    public function getSteps(): int
    {
        return (int) $this->evaluate($this->steps);
    }
}
