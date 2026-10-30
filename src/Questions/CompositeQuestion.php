<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use InvalidArgumentException;

/**
 * A question answered with several inputs, saved together as an array, for
 * example a name, email and phone number. Each part is validated on its own.
 */
abstract class CompositeQuestion extends Question
{
    protected string $view = 'conversational-forms::questions.composite';

    /**
     * @var list<string> | Closure | null
     */
    protected array | Closure | null $parts = null;

    /**
     * @var list<string> | Closure | null
     */
    protected array | Closure | null $requiredParts = null;

    /**
     * Every part this question supports, keyed by name.
     *
     * @return array<string, array{label: string, type?: string, autocomplete?: string, inputmode?: string, rules?: list<string>, width?: 'half' | 'full'}>
     */
    abstract public function getAvailableParts(): array;

    /**
     * The parts required by default when the question is required.
     *
     * @return list<string>
     */
    abstract protected function getDefaultRequiredParts(): array;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (CompositeQuestion $component): array => array_fill_keys(array_keys($component->getParts()), null));
        $this->rule('array');
    }

    /**
     * Which parts to ask for, in order.
     *
     * @param  list<string> | Closure | null  $parts
     */
    public function parts(array | Closure | null $parts): static
    {
        $this->parts = $parts;

        return $this;
    }

    /**
     * Which parts must be filled in when the question is required.
     *
     * @param  list<string> | Closure | null  $parts
     */
    public function requiredParts(array | Closure | null $parts): static
    {
        $this->requiredParts = $parts;

        return $this;
    }

    /**
     * @return array<string, array{label: string, type?: string, autocomplete?: string, inputmode?: string, rules?: list<string>, width?: 'half' | 'full'}>
     */
    public function getParts(): array
    {
        $available = $this->getAvailableParts();
        $names = $this->evaluate($this->parts) ?? array_keys($available);

        $parts = [];

        foreach ($names as $name) {
            if (! array_key_exists($name, $available)) {
                throw new InvalidArgumentException("[{$name}] is not a part of [" . static::class . ']. Available parts: [' . implode(', ', array_keys($available)) . '].');
            }

            $parts[$name] = $available[$name];
        }

        return $parts;
    }

    public function isPartRequired(string $part): bool
    {
        if (! $this->isRequired()) {
            return false;
        }

        return in_array($part, $this->evaluate($this->requiredParts) ?? $this->getDefaultRequiredParts(), strict: true);
    }

    /**
     * The question itself is "required" through its parts.
     *
     * @return array<mixed>
     */
    public function getValidationRules(): array
    {
        return array_values(array_filter(parent::getValidationRules(), fn (mixed $rule): bool => $rule !== 'required'));
    }

    /**
     * @param  array<string, array<mixed>>  $rules
     */
    public function dehydrateValidationRules(array &$rules): void
    {
        if (! $this->passesVisibilityConditions()) {
            return;
        }

        parent::dehydrateValidationRules($rules);

        $statePath = $this->getStatePath();

        foreach ($this->getParts() as $name => $part) {
            $rules["{$statePath}.{$name}"] = [
                $this->isPartRequired($name) ? 'required' : 'nullable',
                'string',
                'max:255',
                ...($part['rules'] ?? []),
            ];
        }
    }

    /**
     * @param  array<string, string>  $attributes
     */
    public function dehydrateValidationAttributes(array &$attributes): void
    {
        parent::dehydrateValidationAttributes($attributes);

        foreach ($this->getParts() as $name => $part) {
            $attributes["{$this->getStatePath()}.{$name}"] = mb_strtolower($part['label']);
        }
    }

    public function formatAnswer(mixed $answer): string
    {
        if (! is_array($answer)) {
            return '';
        }

        return implode(', ', array_filter(
            array_map(fn (string $name): string => trim((string) ($answer[$name] ?? '')), array_keys($this->getParts())),
            filled(...),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            'parts' => $this->evaluate($this->parts),
            'required_parts' => $this->evaluate($this->requiredParts),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        if (array_key_exists('parts', $settings)) {
            $this->parts($settings['parts']);
        }

        if (array_key_exists('required_parts', $settings)) {
            $this->requiredParts($settings['required_parts']);
        }

        return $this;
    }
}
