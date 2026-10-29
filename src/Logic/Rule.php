<?php

namespace JaOcero\ConversationalForms\Logic;

use Illuminate\Contracts\Support\Arrayable;
use JaOcero\ConversationalForms\Logic\Actions\Calculation;
use JaOcero\ConversationalForms\Logic\Enums\CalculationOperator;
use JaOcero\ConversationalForms\Logic\Enums\Operator;
use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JsonSerializable;

/**
 * A logic rule attached to a step. A step's rules are evaluated in order after
 * it is answered: each matching rule runs its calculations, and the first
 * matching rule that jumps or ends decides where to go (later rules are skipped).
 *
 * Rules are plain data (no closures), so they can be serialized, stored and
 * edited in a builder. They are only ever evaluated on the server.
 *
 * @implements Arrayable<string, mixed>
 */
class Rule implements Arrayable, JsonSerializable
{
    protected ?ConditionGroup $conditions = null;

    protected ?string $target = null;

    protected bool $isEnding = false;

    protected ?string $ending = null;

    /**
     * @var list<Calculation>
     */
    protected array $calculations = [];

    final public function __construct() {}

    public static function when(string | Condition | ConditionGroup $field, Operator | string | null $operator = null, mixed $value = null): static
    {
        $rule = new static;

        if ($field instanceof ConditionGroup) {
            $rule->conditions = $field;

            return $rule;
        }

        $rule->conditions = new ConditionGroup(ConditionGroup::ALL, [static::condition($field, $operator, $value)]);

        return $rule;
    }

    public static function always(): static
    {
        return new static;
    }

    public function and(string | Condition | ConditionGroup $field, Operator | string | null $operator = null, mixed $value = null): static
    {
        return $this->addCondition(ConditionGroup::ALL, $field, $operator, $value);
    }

    public function or(string | Condition | ConditionGroup $field, Operator | string | null $operator = null, mixed $value = null): static
    {
        return $this->addCondition(ConditionGroup::ANY, $field, $operator, $value);
    }

    public function jumpTo(string $step): static
    {
        $this->target = $step;
        $this->isEnding = false;

        return $this;
    }

    /**
     * End the conversation now and show the default end screen.
     */
    public function end(): static
    {
        return $this->endWith(null);
    }

    /**
     * End the conversation now and show the end screen with this name.
     */
    public function endWith(?string $ending): static
    {
        $this->target = null;
        $this->isEnding = true;
        $this->ending = $ending;

        return $this;
    }

    public function add(string $variable, int | float $value = 1): static
    {
        return $this->calculate(CalculationOperator::Add, $variable, $value);
    }

    public function subtract(string $variable, int | float $value = 1): static
    {
        return $this->calculate(CalculationOperator::Subtract, $variable, $value);
    }

    public function multiply(string $variable, int | float $value): static
    {
        return $this->calculate(CalculationOperator::Multiply, $variable, $value);
    }

    public function divide(string $variable, int | float $value): static
    {
        return $this->calculate(CalculationOperator::Divide, $variable, $value);
    }

    public function set(string $variable, int | float | string | bool | null $value): static
    {
        return $this->calculate(CalculationOperator::Set, $variable, $value);
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $variables
     */
    public function matches(array $answers, array $variables = []): bool
    {
        return $this->conditions?->matches($answers, $variables) ?? true;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function applyCalculations(array $variables): array
    {
        foreach ($this->calculations as $calculation) {
            $variables = $calculation->apply($variables);
        }

        return $variables;
    }

    public function isAlways(): bool
    {
        return ! $this->conditions instanceof ConditionGroup;
    }

    public function routes(): bool
    {
        return ($this->target !== null) || $this->isEnding;
    }

    public function hasAction(): bool
    {
        return $this->routes() || ($this->calculations !== []);
    }

    public function endsConversation(): bool
    {
        return $this->isEnding;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function getEnding(): ?string
    {
        return $this->ending;
    }

    public function getConditions(): ?ConditionGroup
    {
        return $this->conditions;
    }

    /**
     * @return list<Calculation>
     */
    public function getCalculations(): array
    {
        return $this->calculations;
    }

    /**
     * @return array{if: 'always' | array<string, mixed>, then: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $actions = array_map(fn (Calculation $calculation): array => $calculation->toArray(), $this->calculations);

        if ($this->target !== null) {
            $actions[] = ['jump_to' => $this->target];
        } elseif ($this->isEnding) {
            $actions[] = ($this->ending === null) ? ['end' => true] : ['end_with' => $this->ending];
        }

        return [
            'if' => $this->conditions?->toArray() ?? 'always',
            'then' => $actions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function addCondition(string $combinator, string | Condition | ConditionGroup $field, Operator | string | null $operator, mixed $value): static
    {
        $item = ($field instanceof ConditionGroup) ? $field : static::condition($field, $operator, $value);

        if (! $this->conditions instanceof ConditionGroup) {
            $this->conditions = new ConditionGroup($combinator, [$item]);

            return $this;
        }

        $this->conditions->add($item, $combinator);

        return $this;
    }

    protected function calculate(CalculationOperator $operator, string $variable, int | float | string | bool | null $value): static
    {
        $this->calculations[] = new Calculation($operator, ltrim($variable, '@'), $value);

        return $this;
    }

    protected static function condition(string | Condition $field, Operator | string | null $operator, mixed $value): Condition
    {
        if ($field instanceof Condition) {
            return $field;
        }

        if ($operator === null) {
            throw new InvalidLogicException("The condition on [{$field}] needs an operator.");
        }

        return Condition::make($field, $operator, $value);
    }
}
