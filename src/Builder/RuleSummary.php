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
     */
    public static function describe(array $state, array $steps = []): string
    {
        if (filled($state['raw'] ?? null)) {
            return self::text('summary_code');
        }

        $match = $state['match'] ?? 'all';

        $conditions = array_values(array_filter(array_map(function (mixed $condition): ?string {
            $operator = Operator::tryFrom((string) ($condition['operator'] ?? ''));

            if (blank($condition['field'] ?? null) || ($operator === null)) {
                return null;
            }

            $value = match (true) {
                ! $operator->needsValue() => '',
                $operator->takesList() => ' ' . implode(', ', (array) ($condition['values'] ?? [])),
                default => ' ' . ($condition['value'] ?? '…'),
            };

            return "{$condition['field']} {$operator->getLabel()}{$value}";
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

        $calculations = array_map(
            fn (mixed $calculation): string => ($calculation['operation'] ?? 'add') . ' ' . ($calculation['value'] ?? '') . ' → @' . ltrim((string) ($calculation['variable'] ?? ''), '@'),
            array_values((array) ($state['calculations'] ?? [])),
        );

        return $when . ' → ' . (implode(', ', array_filter([...$calculations, $then])) ?: '…');
    }

    private static function text(string $key): string
    {
        return __("conversational-forms::conversational-forms.builder.form.{$key}");
    }
}
