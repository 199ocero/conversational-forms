<?php

namespace JaOcero\ConversationalForms\Logic;

use JaOcero\ConversationalForms\Logic\Enums\Operator;

/**
 * Entry point for building logic:
 *
 *     Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size')
 *     Logic::when('age', '<', 18)->endWith('not_eligible')
 *     Logic::when('q1', 'is', 'b')->add('score', 10)
 *     Logic::when(Logic::any([
 *         Logic::condition('budget', 'between', [5000, 20000]),
 *         Logic::condition('urgent', 'is', true),
 *     ]))->jumpTo('priority')
 *     Logic::whenVariable('score', '>=', 70)->endWith('passed')
 *     Logic::always()->jumpTo('feedback')
 */
class Logic
{
    public static function when(string | Condition | ConditionGroup $field, Operator | string | null $operator = null, mixed $value = null): Rule
    {
        return Rule::when($field, $operator, $value);
    }

    public static function whenVariable(string $variable, Operator | string $operator, mixed $value = null): Rule
    {
        return Rule::when('@' . ltrim($variable, '@'), $operator, $value);
    }

    public static function always(): Rule
    {
        return Rule::always();
    }

    public static function condition(string $field, Operator | string $operator, mixed $value = null): Condition
    {
        return Condition::make($field, $operator, $value);
    }

    /**
     * Every condition must match (AND).
     *
     * @param  list<Condition | ConditionGroup>  $conditions
     */
    public static function all(array $conditions): ConditionGroup
    {
        return new ConditionGroup(ConditionGroup::ALL, $conditions);
    }

    /**
     * At least one condition must match (OR).
     *
     * @param  list<Condition | ConditionGroup>  $conditions
     */
    public static function any(array $conditions): ConditionGroup
    {
        return new ConditionGroup(ConditionGroup::ANY, $conditions);
    }
}
