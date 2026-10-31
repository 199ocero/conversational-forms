<?php

namespace JaOcero\ConversationalForms\Builder;

use JaOcero\ConversationalForms\Logic\Enums\Operator;

/**
 * A one-line description of a rule in the builder, used as the title of its
 * row: "When plan is enterprise → jump to company".
 */
final class RuleSummary
{
    /**
     * @param  array<string, mixed>  $state  A rule row from `DefinitionState`.
     * @param  array<string, string>  $steps  Step labels keyed by step key.
     * @param  array<string, string>  $questions  Question labels keyed by question key, for conditions.
     */
    public static function describe(array $state, array $steps = [], array $questions = []): string
    {
        if (filled($state['raw'] ?? null)) {
            return self::text('summary_code');
        }

        $match = $state['match'] ?? 'all';

        $conditions = array_values(array_filter(array_map(function (mixed $condition) use ($questions): ?string {
            $operator = Operator::tryFrom((string) ($condition['operator'] ?? ''));

            if (blank($condition['field'] ?? null) || ($operator === null)) {
                return null;
            }

            $value = match (true) {
                ! $operator->needsValue() => '',
                $operator->takesList() => ' ' . implode(', ', (array) ($condition['values'] ?? [])),
                default => ' ' . ($condition['value'] ?? '…'),
            };

            $field = $questions[$condition['field']] ?? $condition['field'];

            return "{$field} {$operator->getLabel()}{$value}";
        }, (array) ($state['conditions'] ?? []))));

        $when = (($match === 'always') || ($conditions === []))
            ? self::text('summary_always')
            : self::text('summary_when') . ' ' . implode(' ' . self::text(($match === 'any') ? 'summary_or' : 'summary_and') . ' ', $conditions);

        $then = match ($state['action'] ?? 'none') {
            'jump_to' => self::text('summary_jump_to') . ' ' . ($steps[$state['target'] ?? ''] ?? $state['target'] ?? '…'),
            'end' => self::text('summary_end'),
            'end_with' => self::text('summary_end_with') . ' ' . ($state['ending'] ?? '…'),
            default => null,
        };

        // Calculations without a variable are dropped when the draft is saved, so they aren't described.
        $calculations = array_map(
            fn (array $calculation): string => __('conversational-forms::conversational-forms.builder.form.summary_calculation_' . ($calculation['operation'] ?? 'add'), [
                'variable' => '@' . ltrim((string) $calculation['variable'], '@'),
                'value' => filled($calculation['value'] ?? null) ? (string) $calculation['value'] : '…',
            ]),
            array_values(array_filter(
                (array) ($state['calculations'] ?? []),
                fn (mixed $calculation): bool => is_array($calculation) && filled($calculation['variable'] ?? null),
            )),
        );

        return $when . ' → ' . (implode(', ', array_filter([...$calculations, $then])) ?: '…');
    }

    private static function text(string $key): string
    {
        return __("conversational-forms::conversational-forms.builder.form.{$key}");
    }
}
