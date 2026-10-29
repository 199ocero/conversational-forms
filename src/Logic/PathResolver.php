<?php

namespace JaOcero\ConversationalForms\Logic;

/**
 * Walks the steps of a conversation in order, applying each step's rules to
 * decide where to go next. The result is deterministic for a given set of
 * answers, so the server can always recompute it instead of trusting the client.
 */
final class PathResolver
{
    /**
     * @param  list<string>  $steps  Step names in display order.
     * @param  array<string, list<Rule>>  $rules  Rules keyed by the step they are attached to.
     * @param  array<string, list<string>>  $stepFields  The state paths of the fields on each step.
     * @param  array<string, mixed>  $answers
     */
    public function resolve(array $steps, array $rules, array $stepFields, array $answers): ConversationPath
    {
        $positions = array_flip($steps);
        $path = [];
        $index = 0;

        while (isset($steps[$index])) {
            $step = $steps[$index];
            $path[] = $step;

            $rule = $this->findMatchingRule(
                $rules[$step] ?? [],
                $this->answersVisibleFrom($path, $stepFields, $answers),
            );

            if ($rule?->endsConversation()) {
                break;
            }

            $target = $rule?->getTarget();
            $targetIndex = ($target === null) ? null : ($positions[$target] ?? null);

            // Jumps only move forward (enforced by LogicGraph), which guarantees
            // termination. A stale or invalid target falls through to the next step.
            $index = (($targetIndex !== null) && ($targetIndex > $index)) ? $targetIndex : ($index + 1);
        }

        return new ConversationPath($path);
    }

    /**
     * @param  list<Rule>  $rules
     * @param  array<string, mixed>  $answers
     */
    private function findMatchingRule(array $rules, array $answers): ?Rule
    {
        foreach ($rules as $rule) {
            if (($rule->getTarget() !== null) && $rule->matches($answers)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Answers given on steps that are not on the path (for example a question
     * the respondent answered before changing an earlier answer) must not
     * influence later rules, so they are hidden from evaluation.
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
