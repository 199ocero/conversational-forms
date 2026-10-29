<?php

namespace JaOcero\ConversationalForms\Logic;

/**
 * Walks the steps of a conversation in order and applies their logic: skips
 * steps whose visibility conditions fail, runs calculations, follows jumps and
 * stops at endings. The result depends only on the answers, so the server can
 * always recompute it instead of trusting the browser.
 */
final class PathResolver
{
    /**
     * @param  list<string>  $steps  Step names in display order.
     * @param  array<string, list<Rule>>  $rules  Rules keyed by the step they follow.
     * @param  array<string, list<string>>  $stepFields  The state paths of each step's fields.
     * @param  array<string, mixed>  $answers
     * @param  array<string, ConditionGroup>  $stepConditions  Steps that are only shown when their conditions match.
     * @param  array<string, mixed>  $variables  Starting values of variables.
     */
    public function resolve(
        array $steps,
        array $rules,
        array $stepFields,
        array $answers,
        array $stepConditions = [],
        array $variables = [],
        ?string $defaultEnding = null,
    ): ConversationPath {
        $positions = array_flip($steps);
        $path = [];
        $ending = null;
        $index = 0;

        while (isset($steps[$index])) {
            $step = $steps[$index];

            $conditions = $stepConditions[$step] ?? null;

            if ($conditions && (! $conditions->matches($this->answersVisibleFrom($path, $stepFields, $answers), $variables))) {
                $index++;

                continue;
            }

            $path[] = $step;
            $visibleAnswers = $this->answersVisibleFrom($path, $stepFields, $answers);
            $route = null;

            // Rules run in order until one that matches also routes (jumps or
            // ends). Calculations of matching rules up to that point apply, so
            // `always()` placed last acts as a fallback.
            foreach ($rules[$step] ?? [] as $rule) {
                if (! $rule->matches($visibleAnswers, $variables)) {
                    continue;
                }

                $variables = $rule->applyCalculations($variables);

                if ($rule->routes()) {
                    $route = $rule;

                    break;
                }
            }

            if ($route?->endsConversation()) {
                $ending = $route->getEnding();

                break;
            }

            $target = $route?->getTarget();
            $targetIndex = ($target === null) ? null : ($positions[$target] ?? null);

            // Jumps only move forward (enforced by LogicGraph), which guarantees
            // termination. A stale or invalid target falls through to the next step.
            $index = (($targetIndex !== null) && ($targetIndex > $index)) ? $targetIndex : ($index + 1);
        }

        return new ConversationPath($path, $ending ?? $defaultEnding, $variables);
    }

    /**
     * Answers given on steps that are not on the path (for example a question
     * answered before an earlier answer changed) must not influence later
     * rules, so they are hidden from evaluation.
     *
     * @param  list<string>  $path
     * @param  array<string, list<string>>  $stepFields
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function answersVisibleFrom(array $path, array $stepFields, array $answers): array
    {
        foreach ($stepFields as $step => $fields) {
            if (in_array($step, $path, strict: true)) {
                continue;
            }

            foreach ($fields as $field) {
                data_forget($answers, $field);
            }
        }

        return $answers;
    }
}
