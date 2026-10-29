<?php

namespace JaOcero\ConversationalForms\Logic;

use Illuminate\Contracts\Support\Arrayable;
use JaOcero\ConversationalForms\Logic\Enums\Operator;
use JsonSerializable;

/**
 * Compares an answer (`plan`) or a variable (`@score`) with a value.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Condition implements Arrayable, JsonSerializable
{
    public mixed $value;

    public function __construct(
        public string $field,
        public Operator $operator,
        mixed $value = null,
    ) {
        $this->value = Values::normalize($value);
    }

    public static function make(string $field, Operator | string $operator, mixed $value = null): self
    {
        return new self($field, Operator::resolve($operator), $value);
    }

    /**
     * @param  array{field: string, op: string, value?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        return self::make($data['field'], $data['op'], $data['value'] ?? null);
    }

    public function isVariable(): bool
    {
        return str_starts_with($this->field, '@');
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $variables
     */
    public function matches(array $answers, array $variables = []): bool
    {
        $subject = $this->isVariable()
            ? ($variables[substr($this->field, 1)] ?? null)
            : data_get($answers, $this->field);

        return $this->operator->evaluate($subject, $this->value);
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
