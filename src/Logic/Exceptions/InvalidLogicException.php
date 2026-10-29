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
        return new self("A logic rule on [{$step}] has no action. Call [jumpTo()], [end()], [endWith()] or a calculation such as [add()] on it.");
    }

    public static function unknownEnding(string $step, string $ending): self
    {
        return new self("A logic rule on [{$step}] ends with [{$ending}], but no end screen with that name exists in the conversation.");
    }

    public static function variablesInVisibility(string $field): self
    {
        return new self("The visibility conditions of [{$field}] use a variable. Variables are only calculated on the server, so visibility can only depend on answers.");
    }
}
