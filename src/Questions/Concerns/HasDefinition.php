<?php

namespace JaOcero\ConversationalForms\Questions\Concerns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use JaOcero\ConversationalForms\Logic\ConditionGroup;
use JaOcero\ConversationalForms\Logic\Rule;

/**
 * Turns a question into plain data (a "definition") and back, so
 * conversations can be stored as JSON and edited in the builder. Closures
 * are evaluated when the definition is made.
 *
 * Each question adds its own options through `getDefinitionSettings()` and
 * `definitionSettings()`. Rules are stored on the step, not the question (see
 * `ConversationDefinition`).
 *
 * @phpstan-type QuestionDefinition array{key: string, label?: string, description?: string, required?: bool, media?: array{url: string, alt: string}, visible_when?: array<string, mixed>, correct?: array{answer: mixed, points: int|float, variable: string}, settings?: array<string, mixed>}
 */
trait HasDefinition
{
    /**
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        $definition = [
            'key' => $this->getName(),
            'label' => static::definitionText($this->evaluate($this->label)),
            'description' => static::definitionText($this->evaluate($this->description)),
            'required' => $this->isRequired() ?: null,
            'media' => $this->getMediaDefinition(),
            'visible_when' => $this->getVisibilityConditions()?->toArray(),
            'correct' => method_exists($this, 'getCorrectAnswerDefinition') ? $this->getCorrectAnswerDefinition() : null,
            'settings' => array_filter($this->getDefinitionSettings(), fn (mixed $value): bool => $value !== null) ?: null,
        ];

        return array_filter($definition, fn (mixed $value): bool => $value !== null);
    }

    /**
     * Configure the question from a definition made by `toDefinition()`.
     *
     * @param  array<string, mixed>  $definition
     */
    public function fillFromDefinition(array $definition): static
    {
        if (filled($definition['label'] ?? null)) {
            $this->label($definition['label']);
        }

        if (filled($definition['description'] ?? null)) {
            $this->description($definition['description']);
        }

        $this->required((bool) ($definition['required'] ?? false));

        if (filled($definition['media']['url'] ?? null)) {
            $this->media($definition['media']['url'], $definition['media']['alt'] ?? '');
        }

        if (filled($definition['visible_when'] ?? null)) {
            $this->visibleWhen(ConditionGroup::fromArray($definition['visible_when']));
        }

        if (isset($definition['correct']) && method_exists($this, 'fillCorrectAnswerFromDefinition')) {
            $this->fillCorrectAnswerFromDefinition($definition['correct']);
        }

        $this->definitionSettings($definition['settings'] ?? []);

        return $this;
    }

    /**
     * This question's own options, such as a placeholder or the choices.
     * `null` values are left out of the definition.
     *
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [];
    }

    /**
     * Apply options saved by `getDefinitionSettings()`.
     *
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        return $this;
    }

    /**
     * The rules attached to the question itself with `logic()`, without the
     * ones generated from `correct()` and `points()`.
     *
     * @return list<Rule>
     */
    public function getOwnLogic(): array
    {
        return array_values($this->evaluate($this->logic));
    }

    /**
     * Whether a validation rule such as `accepted` was added with `rule()` or
     * a shorthand like `accepted()`.
     */
    protected function hasDefinitionRule(string $name): bool
    {
        foreach ($this->rules as [$rule, $condition]) {
            if (($rule === $name) && $this->evaluate($condition)) {
                return true;
            }
        }

        return false;
    }

    protected static function definitionText(mixed $text): ?string
    {
        return match (true) {
            $text instanceof Htmlable => $text->toHtml(),
            $text instanceof Closure, blank($text) => null,
            default => (string) $text,
        };
    }

    /**
     * Options as an ordered list (keys like `"01"` or `1` survive JSON).
     *
     * @param  array<array-key, mixed>  $options
     * @return list<array{value: string, label: string}>
     */
    protected static function optionsToDefinition(array $options): array
    {
        $list = [];

        foreach ($options as $value => $label) {
            $list[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $list;
    }

    /**
     * @param  list<array{value: string|int, label?: string|null}>  $options
     * @return array<string, string>
     */
    protected static function optionsFromDefinition(array $options): array
    {
        $map = [];

        foreach ($options as $option) {
            $map[(string) $option['value']] = (string) ($option['label'] ?? $option['value']);
        }

        return $map;
    }
}
