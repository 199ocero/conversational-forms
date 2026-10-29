<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;

/**
 * A number, saved as an integer or a float.
 */
class Number extends ShortText
{
    protected int | float | Closure | null $minValue = null;

    protected int | float | Closure | null $maxValue = null;

    protected bool | Closure $isInteger = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('numeric');
        $this->rule('integer', fn (Number $component): bool => $component->isInteger());
        $this->rule(fn (Number $component): string => "min:{$component->getMinValue()}", fn (Number $component): bool => $component->getMinValue() !== null);
        $this->rule(fn (Number $component): string => "max:{$component->getMaxValue()}", fn (Number $component): bool => $component->getMaxValue() !== null);
        $this->inputMode(fn (Number $component): string => $component->isInteger() ? 'numeric' : 'decimal');

        $this->dehydrateStateUsing(fn (Number $component, mixed $state): int | float | null => $component->castNumber($state));
    }

    public function minValue(int | float | Closure | null $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    public function maxValue(int | float | Closure | null $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    public function integer(bool | Closure $condition = true): static
    {
        $this->isInteger = $condition;

        return $this;
    }

    public function getMinValue(): int | float | null
    {
        return $this->evaluate($this->minValue);
    }

    public function getMaxValue(): int | float | null
    {
        return $this->evaluate($this->maxValue);
    }

    public function isInteger(): bool
    {
        return (bool) $this->evaluate($this->isInteger);
    }

    /**
     * Only digits (and one decimal point for decimals) can be typed. A leading
     * minus sign is allowed unless the minimum value rules out negative numbers.
     */
    public function getInputFilter(): string
    {
        $minValue = $this->getMinValue();
        $sign = (($minValue === null) || ($minValue < 0)) ? '-?' : '';

        return $this->isInteger() ? "^{$sign}\\d*$" : "^{$sign}\\d*(\\.\\d*)?$";
    }

    public function castNumber(mixed $state): int | float | null
    {
        if (! is_numeric($state)) {
            return null;
        }

        return $this->isInteger() ? (int) $state : (float) $state;
    }

    public function getPlaceholder(): string
    {
        return $this->evaluate($this->placeholder) ?? __('conversational-forms::conversational-forms.questions.number_placeholder');
    }
}
