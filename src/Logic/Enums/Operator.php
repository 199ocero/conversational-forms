<?php

namespace JaOcero\ConversationalForms\Logic\Enums;

use BackedEnum;
use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;

enum Operator: string
{
    case Is = 'is';

    case IsNot = 'is_not';

    public static function resolve(Operator | string $operator): self
    {
        if ($operator instanceof self) {
            return $operator;
        }

        return self::tryFrom($operator) ?? match ($operator) {
            '=', '==', '===' => self::Is,
            '!=', '!==', '<>' => self::IsNot,
            default => throw InvalidLogicException::unknownOperator($operator),
        };
    }

    public function evaluate(mixed $answer, mixed $value): bool
    {
        return match ($this) {
            self::Is => self::normalize($answer) === self::normalize($value),
            self::IsNot => self::normalize($answer) !== self::normalize($value),
        };
    }

    /**
     * Livewire sends numbers from inputs as strings, so scalars are compared by
     * their string form. Booleans and `null` keep their type so that `false`
     * never equals an unanswered question.
     */
    protected static function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value === '') {
            return null;
        }

        return $value;
    }
}
