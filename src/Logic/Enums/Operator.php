<?php

namespace JaOcero\ConversationalForms\Logic\Enums;

use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JaOcero\ConversationalForms\Logic\Values;

/**
 * Comparison operators for logic conditions.
 *
 * The semantics are mirrored exactly by `resources/js/logic/evaluate.js`; both
 * implementations run the shared cases in `tests/fixtures/logic/operators.json`.
 */
enum Operator: string
{
    // Any answer
    case Is = 'is';
    case IsNot = 'is_not';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';

    // Text
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';

    // Multiple answers
    case IncludesAny = 'includes_any';
    case IncludesAll = 'includes_all';
    case Excludes = 'excludes';

    // Numbers
    case GreaterThan = 'gt';
    case GreaterThanOrEqual = 'gte';
    case LessThan = 'lt';
    case LessThanOrEqual = 'lte';
    case Between = 'between';

    // Dates (YYYY-MM-DD)
    case Before = 'before';
    case After = 'after';
    case On = 'on';
    case NotOn = 'not_on';

    public static function resolve(Operator | string $operator): self
    {
        if ($operator instanceof self) {
            return $operator;
        }

        return self::tryFrom($operator) ?? match ($operator) {
            '=', '==', '===' => self::Is,
            '!=', '!==', '<>' => self::IsNot,
            '>' => self::GreaterThan,
            '>=' => self::GreaterThanOrEqual,
            '<' => self::LessThan,
            '<=' => self::LessThanOrEqual,
            default => throw InvalidLogicException::unknownOperator($operator),
        };
    }

    public function evaluate(mixed $answer, mixed $value): bool
    {
        $answer = Values::normalize($answer);
        $value = Values::normalize($value);

        return match ($this) {
            self::Is => Values::is($answer, $value),
            self::IsNot => ! Values::is($answer, $value),
            self::IsEmpty => Values::isEmpty($answer),
            self::IsNotEmpty => ! Values::isEmpty($answer),
            self::Contains => Values::textContains($answer, $value),
            self::NotContains => ! Values::textContains($answer, $value),
            self::StartsWith => Values::textStartsWith($answer, $value),
            self::EndsWith => Values::textEndsWith($answer, $value),
            self::IncludesAny => Values::includesAny($answer, $value),
            self::IncludesAll => Values::includesAll($answer, $value),
            self::Excludes => ! Values::includesAny($answer, $value),
            self::GreaterThan => Values::compareNumbers($answer, $value, fn (float $a, float $b): bool => $a > $b),
            self::GreaterThanOrEqual => Values::compareNumbers($answer, $value, fn (float $a, float $b): bool => $a >= $b),
            self::LessThan => Values::compareNumbers($answer, $value, fn (float $a, float $b): bool => $a < $b),
            self::LessThanOrEqual => Values::compareNumbers($answer, $value, fn (float $a, float $b): bool => $a <= $b),
            self::Between => Values::between($answer, $value),
            self::Before => Values::compareDates($answer, $value, fn (string $a, string $b): bool => $a < $b),
            self::After => Values::compareDates($answer, $value, fn (string $a, string $b): bool => $a > $b),
            self::On => Values::compareDates($answer, $value, fn (string $a, string $b): bool => $a === $b),
            self::NotOn => Values::compareDates($answer, $value, fn (string $a, string $b): bool => $a !== $b),
        };
    }
}
