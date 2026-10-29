<?php

namespace JaOcero\ConversationalForms\Logic;

use Illuminate\Contracts\Support\Arrayable;
use JaOcero\ConversationalForms\Logic\Enums\Operator;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class Condition implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $field,
        public Operator $operator,
        public mixed $value,
    ) {}

    public static function make(string $field, Operator | string $operator, mixed $value = null): self
    {
        return new self($field, Operator::resolve($operator), $value);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public function matches(array $answers): bool
    {
        return $this->operator->evaluate(data_get($answers, $this->field), $this->value);
    }

    /**
     * @return array{field: string, op: string, value: mixed}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'op' => $this->operator->value,
            'value' => $this->value,
        ];
    }

    /**
     * @return array{field: string, op: string, value: mixed}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
