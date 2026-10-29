<?php

namespace JaOcero\ConversationalForms\Logic;

use JaOcero\ConversationalForms\Logic\Enums\Operator;

/**
 * Entry point for building logic rules:
 *
 *     Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size')
 *     Logic::always()->jumpTo('feedback')
 */
class Logic
{
    public static function when(string $field, Operator | string $operator, mixed $value = null): Rule
    {
        return Rule::when($field, $operator, $value);
    }

    public static function always(): Rule
    {
        return Rule::always();
    }
}
