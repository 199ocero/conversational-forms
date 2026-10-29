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
     *
     * @throws InvalidLogicException
     */
    public function validate(array $steps, array $rules): void
    {
        $positions = array_flip($steps);

        foreach ($rules as $step => $stepRules) {
            if (! array_key_exists($step, $positions)) {
                throw InvalidLogicException::unknownStep($step);
            }

            foreach ($stepRules as $rule) {
                $target = $rule->getTarget();

                if ($target === null) {
                    throw InvalidLogicException::missingTarget($step);
                }

                if ($rule->endsConversation()) {
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
