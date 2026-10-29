<?php

namespace JaOcero\ConversationalForms\Support;

use BackedEnum;
use Stringable;

/**
 * Formats answers that have no question of their own to describe them.
 */
final class Answer
{
    public static function format(mixed $answer): string
    {
        return match (true) {
            $answer === null => '',
            is_bool($answer) => __('conversational-forms::conversational-forms.questions.' . ($answer ? 'yes' : 'no')),
            is_array($answer) => implode(', ', array_filter(array_map(self::format(...), $answer), filled(...))),
            $answer instanceof BackedEnum => (string) $answer->value,
            is_scalar($answer), $answer instanceof Stringable => (string) $answer,
            default => '',
        };
    }
}
