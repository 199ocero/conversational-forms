<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use Filament\Forms\Components\Concerns\CanBeLengthConstrained;
use Filament\Forms\Components\Concerns\HasExtraInputAttributes;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Contracts\CanBeLengthConstrained as CanBeLengthConstrainedContract;

class LongText extends Question implements CanBeLengthConstrainedContract
{
    use CanBeLengthConstrained;
    use HasExtraInputAttributes;
    use HasPlaceholder;

    protected string $view = 'conversational-forms::questions.long-text';

    protected bool | Closure $shouldSubmitOnEnter = true;

    protected int | Closure $rows = 3;

    /**
     * By default Enter moves on and Shift + Enter adds a line break. Pass
     * `false` to make Enter add a line break; Ctrl / Cmd + Enter then moves on.
     */
    public function submitOnEnter(bool | Closure $condition = true): static
    {
        $this->shouldSubmitOnEnter = $condition;

        return $this;
    }

    public function shouldSubmitOnEnter(): bool
    {
        return (bool) $this->evaluate($this->shouldSubmitOnEnter);
    }

    public function rows(int | Closure $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function getRows(): int
    {
        return (int) $this->evaluate($this->rows);
    }

    public function getPlaceholder(): string
    {
        return $this->evaluate($this->placeholder) ?? __('conversational-forms::conversational-forms.questions.placeholder');
    }
}
