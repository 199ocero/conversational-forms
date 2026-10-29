<?php

namespace JaOcero\ConversationalForms\Logic;

use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;

/**
 * Validates a conversation's logic when it is built, so mistakes surface as a
 * clear exception during development instead of a broken path in production.
 */
final class LogicGraph
{
    /**
     * @param  list<string>  $steps
     * @param  array<string, list<Rule>>  $rules
     * @param  list<string>  $endings  The names of the conversation's end screens.
     *
     * @throws InvalidLogicException
     */
    public function validate(array $steps, array $rules, array $endings = []): void
    {
        $positions = array_flip($steps);

        foreach ($rules as $step => $stepRules) {
            if (! array_key_exists($step, $positions)) {
                throw InvalidLogicException::unknownStep($step);
            }

            foreach ($stepRules as $rule) {
                if (! $rule->hasAction()) {
                    throw InvalidLogicException::missingTarget($step);
                }

                if ($rule->endsConversation()) {
                    $ending = $rule->getEnding();

                    if (($ending !== null) && (! in_array($ending, $endings, strict: true))) {
                        throw InvalidLogicException::unknownEnding($step, $ending);
                    }

                    continue;
                }

                $target = $rule->getTarget();

                if ($target === null) {
                    continue;
                }

                if (! array_key_exists($target, $positions)) {
                    throw InvalidLogicException::unknownTarget($step, $target);
                }

                if ($positions[$target] <= $positions[$step]) {
                    throw InvalidLogicException::backwardJump($step, $target);
                }
            }
        }
    }
}
