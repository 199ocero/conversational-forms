<?php

namespace JaOcero\ConversationalForms\Logic;

use Illuminate\Contracts\Support\Arrayable;
use JaOcero\ConversationalForms\Logic\Enums\Operator;
use JsonSerializable;

/**
 * A logic rule attached to a question. It is evaluated after the question is
 * answered: when every condition matches, the conversation jumps to the target.
 *
 * Rules are plain data (no closures), so the same rule can be serialized,
 * evaluated in PHP or JavaScript, and edited in a builder.
 *
 * @implements Arrayable<string, mixed>
 */
class Rule implements Arrayable, JsonSerializable
{
    /**
     * The target of a rule that ends the conversation early.
     */
    public const END = '@end';

    /**
     * @var array<Condition>
     */
    protected array $conditions = [];

    protected ?string $target = null;

    final public function __construct() {}

    public static function when(string $field, Operator | string $operator, mixed $value = null): static
    {
        return (new static)->and($field, $operator, $value);
    }

    public static function always(): static
    {
        return new static;
    }

    public function and(string $field, Operator | string $operator, mixed $value = null): static
    {
        $this->conditions[] = Condition::make($field, $operator, $value);

        return $this;
    }

    public function jumpTo(string $step): static
    {
        $this->target = $step;

        return $this;
    }

    public function end(): static
    {
        $this->target = static::END;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public function matches(array $answers): bool
    {
        foreach ($this->conditions as $condition) {
            if (! $condition->matches($answers)) {
                return false;
            }
        }

        return true;
    }

    public function isAlways(): bool
    {
        return $this->conditions === [];
    }

    public function endsConversation(): bool
    {
        return $this->target === static::END;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    /**
     * @return array<Condition>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * @return array{if: 'always' | array{all: array<array{field: string, op: string, value: mixed}>}, then: array{jump_to: string} | array{end: true} | null}
     */
    public function toArray(): array
    {
        return [
            'if' => $this->isAlways()
                ? 'always'
                : ['all' => array_map(fn (Condition $condition): array => $condition->toArray(), $this->conditions)],
            'then' => match (true) {
                $this->target === null => null,
                $this->endsConversation() => ['end' => true],
                default => ['jump_to' => $this->target],
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
