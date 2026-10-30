<?php

namespace JaOcero\ConversationalForms\Definitions\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a stored conversation (a definition) can't be built, for
 * example because of an unknown question type or a duplicate key.
 */
class InvalidDefinitionException extends InvalidArgumentException
{
    public static function unsupportedVersion(int $version): self
    {
        return new self("This conversation was saved by a newer version of the package (schema version [{$version}]).");
    }

    public static function unknownType(string $type): self
    {
        return new self("There is no question type called [{$type}]. Register it with [QuestionTypeRegistry::register()].");
    }

    public static function unregisteredQuestion(string $class): self
    {
        return new self("The question [{$class}] has no registered question type, so it can't be saved as a definition.");
    }

    public static function invalidKey(string $key): self
    {
        return new self("[{$key}] can't be used as a key: use letters, digits and underscores, starting with a letter.");
    }

    public static function duplicateKey(string $key): self
    {
        return new self("The key [{$key}] is used more than once.");
    }

    public static function unknownField(string $step, string $field): self
    {
        return new self("A condition on [{$step}] refers to [{$field}], which isn't a question in this conversation.");
    }
}
