<?php

namespace JaOcero\ConversationalForms\Definitions;

use Filament\Forms\Components\Field;
use JaOcero\ConversationalForms\Definitions\Exceptions\InvalidDefinitionException;

/**
 * The question types that definitions and the builder know about. The
 * package registers its own; add yours from a service provider:
 *
 *     app(QuestionTypeRegistry::class)->register(QuestionType::make('payment', Payment::class));
 */
final class QuestionTypeRegistry
{
    /**
     * @var array<string, QuestionType>
     */
    private array $types = [];

    public function register(QuestionType ...$types): self
    {
        foreach ($types as $type) {
            $this->types[$type->getName()] = $type;
        }

        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->types);
    }

    /**
     * @throws InvalidDefinitionException
     */
    public function get(string $name): QuestionType
    {
        return $this->types[$name] ?? throw InvalidDefinitionException::unknownType($name);
    }

    /**
     * The type of a question: the one registered for its exact class, else
     * for its closest registered parent class.
     *
     * @throws InvalidDefinitionException
     */
    public function forQuestion(Field $question): QuestionType
    {
        for ($class = $question::class; $class !== false; $class = get_parent_class($class)) {
            foreach ($this->types as $type) {
                if ($type->getQuestionClass() === $class) {
                    return $type;
                }
            }
        }

        throw InvalidDefinitionException::unregisteredQuestion($question::class);
    }

    /**
     * @return array<string, QuestionType>
     */
    public function all(): array
    {
        return $this->types;
    }
}
