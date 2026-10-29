<?php

namespace JaOcero\ConversationalForms\Logic\Exceptions;

use InvalidArgumentException;

class InvalidLogicException extends InvalidArgumentException
{
    public static function unknownOperator(string $operator): self
    {
        return new self("The logic operator [{$operator}] is not supported.");
    }

    public static function unknownStep(string $step): self
    {
        return new self("Logic is attached to [{$step}], but no step with that name exists in the conversation.");
    }

    public static function unknownTarget(string $step, string $target): self
    {
        return new self("A logic rule on [{$step}] jumps to [{$target}], but no step with that name exists in the conversation.");
    }

    public static function backwardJump(string $step, string $target): self
    {
        return new self("A logic rule on [{$step}] jumps to [{$target}], which does not come after it. Jumps may only move forward, so a conversation can never loop.");
    }

    public static function missingTarget(string $step): self
    {
        return new self("A logic rule on [{$step}] has conditions but no action. Call [jumpTo()] or [end()] on it.");
    }
}
