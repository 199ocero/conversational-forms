<?php

namespace JaOcero\ConversationalForms\Logic\Actions;

use Illuminate\Contracts\Support\Arrayable;
use JaOcero\ConversationalForms\Logic\Enums\CalculationOperator;

/**
 * Changes a variable, for example `add('score', 10)`. Variables start at 0
 * unless the conversation gives them another starting value.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Calculation implements Arrayable
{
    public function __construct(
        public CalculationOperator $operator,
        public string $variable,
        public int | float | string | bool | null $value,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function apply(array $variables): array
    {
        $current = $variables[$this->variable] ?? 0;

        if ($this->operator === CalculationOperator::Set) {
            $variables[$this->variable] = $this->value;

            return $variables;
        }

        $current = is_numeric($current) ? $current + 0 : 0;
        $value = is_numeric($this->value) ? $this->value + 0 : 0;

        $variables[$this->variable] = match ($this->operator) {
            CalculationOperator::Add => $current + $value,
            CalculationOperator::Subtract => $current - $value,
            CalculationOperator::Multiply => $current * $value,
            CalculationOperator::Divide => ($value == 0) ? $current : $current / $value,
        };

        return $variables;
    }

    /**
     * @return array<string, array{0: string, 1: int|float|string|bool|null}>
     */
    public function toArray(): array
    {
        return [$this->operator->value => [$this->variable, $this->value]];
    }
}
