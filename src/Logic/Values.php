<?php

namespace JaOcero\ConversationalForms\Logic;

use BackedEnum;
use DateTimeInterface;

/**
 * Value helpers behind the logic operators. Every rule here has an identical
 * counterpart in `resources/js/logic/evaluate.js`.
 *
 * @internal
 */
final class Values
{
    private const NUMBER = '/^\s*[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?\s*$/';

    private const DATE = '/^\d{4}-\d{2}-\d{2}/';

    /**
     * Turn enums and dates into the plain values they are stored as, and treat
     * an empty string as "no answer".
     */
    public static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_array($value) => array_map(self::normalize(...), array_values($value)),
            $value === '' => null,
            default => $value,
        };
    }

    public static function isNumber(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value);
        }

        return is_string($value) && (preg_match(self::NUMBER, $value) === 1);
    }

    public static function equals(mixed $a, mixed $b): bool
    {
        if (($a === null) || ($b === null) || is_bool($a) || is_bool($b)) {
            return $a === $b;
        }

        if (self::isNumber($a) && self::isNumber($b)) {
            return (float) $a === (float) $b;
        }

        return self::text($a) === self::text($b);
    }

    public static function is(mixed $answer, mixed $value): bool
    {
        if (is_array($answer)) {
            foreach ($answer as $item) {
                if (self::equals($item, $value)) {
                    return true;
                }
            }

            return false;
        }

        return self::equals($answer, $value);
    }

    public static function isEmpty(mixed $answer): bool
    {
        return ($answer === null)
            || (is_string($answer) && (trim($answer) === ''))
            || (is_array($answer) && ($answer === []));
    }

    public static function textContains(mixed $answer, mixed $value): bool
    {
        return self::anyText($answer, fn (string $text): bool => str_contains($text, self::lower($value)));
    }

    public static function textStartsWith(mixed $answer, mixed $value): bool
    {
        return self::anyText($answer, fn (string $text): bool => str_starts_with($text, self::lower($value)));
    }

    public static function textEndsWith(mixed $answer, mixed $value): bool
    {
        return self::anyText($answer, fn (string $text): bool => str_ends_with($text, self::lower($value)));
    }

    public static function includesAny(mixed $answer, mixed $values): bool
    {
        $answers = self::list($answer);

        foreach (self::list($values) as $value) {
            foreach ($answers as $item) {
                if (self::equals($item, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function includesAll(mixed $answer, mixed $values): bool
    {
        $values = self::list($values);

        if ($values === []) {
            return false;
        }

        foreach ($values as $value) {
            if (! self::is(self::list($answer), $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  callable(float, float): bool  $compare
     */
    public static function compareNumbers(mixed $answer, mixed $value, callable $compare): bool
    {
        if ((! self::isNumber($answer)) || (! self::isNumber($value))) {
            return false;
        }

        return $compare((float) $answer, (float) $value);
    }

    /**
     * @param  callable(string, string): bool  $compare
     */
    public static function compareDates(mixed $answer, mixed $value, callable $compare): bool
    {
        $answer = self::date($answer);
        $value = self::date($value);

        if (($answer === null) || ($value === null)) {
            return false;
        }

        return $compare($answer, $value);
    }

    /**
     * Inclusive, for numbers or dates: `[min, max]`.
     */
    public static function between(mixed $answer, mixed $range): bool
    {
        if ((! is_array($range)) || (count($range) !== 2)) {
            return false;
        }

        [$min, $max] = array_values($range);

        if (self::isNumber($answer) && self::isNumber($min) && self::isNumber($max)) {
            return ((float) $answer >= (float) $min) && ((float) $answer <= (float) $max);
        }

        $answer = self::date($answer);
        $min = self::date($min);
        $max = self::date($max);

        if (($answer === null) || ($min === null) || ($max === null)) {
            return false;
        }

        return ($answer >= $min) && ($answer <= $max);
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? array_values($value) : [$value];
    }

    /**
     * @param  callable(string): bool  $test
     */
    private static function anyText(mixed $answer, callable $test): bool
    {
        foreach (self::list($answer) as $item) {
            if ($test(self::lower($item))) {
                return true;
            }
        }

        return false;
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    private static function lower(mixed $value): string
    {
        return mb_strtolower(self::text($value));
    }

    private static function date(mixed $value): ?string
    {
        return (is_string($value) && (preg_match(self::DATE, $value) === 1)) ? substr($value, 0, 10) : null;
    }
}
