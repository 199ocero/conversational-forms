<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;

/**
 * A grid of statements (rows) rated on the same answers (shown as columns),
 * for example "How satisfied are you with…" × "Not at all … Very". Saved as
 * `['row' => 'answer']`, or `['row' => ['answer', …]]` with `multiple()`.
 */
class Matrix extends Question
{
    protected string $view = 'conversational-forms::questions.matrix';

    /**
     * @var array<string, string> | Closure
     */
    protected array | Closure $rows = [];

    /**
     * @var array<string, string> | Closure
     */
    protected array | Closure $answers = [];

    protected bool | Closure $isMultiple = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (Matrix $component): array => array_fill_keys(array_keys($component->getRows()), $component->isMultiple() ? [] : null));
        $this->rule('array');
    }

    /**
     * @param  array<string, string> | Closure  $rows
     */
    public function rows(array | Closure $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * The answers every row is rated on, shown as columns.
     *
     * @param  array<string, string> | Closure  $answers
     */
    public function answers(array | Closure $answers): static
    {
        $this->answers = $answers;

        return $this;
    }

    /**
     * Allow several answers per row.
     */
    public function multiple(bool | Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getRows(): array
    {
        return array_map(strval(...), $this->evaluate($this->rows));
    }

    /**
     * @return array<string, string>
     */
    public function getAnswerOptions(): array
    {
        return array_map(strval(...), $this->evaluate($this->answers));
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    /**
     * Every row must be answered when the question is required.
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
        $columns = 'in:' . implode(',', array_keys($this->getAnswerOptions()));
        $presence = $this->isRequired() ? 'required' : 'nullable';

        foreach (array_keys($this->getRows()) as $row) {
            if ($this->isMultiple()) {
                $rules["{$statePath}.{$row}"] = [$presence, 'array'];
                $rules["{$statePath}.{$row}.*"] = [$columns];

                continue;
            }

            $rules["{$statePath}.{$row}"] = [$presence, $columns];
        }
    }

    /**
     * @param  array<string, string>  $attributes
     */
    public function dehydrateValidationAttributes(array &$attributes): void
    {
        parent::dehydrateValidationAttributes($attributes);

        foreach ($this->getRows() as $row => $label) {
            $attributes["{$this->getStatePath()}.{$row}"] = $label;
        }
    }

    public function formatAnswer(mixed $answer): string
    {
        if (! is_array($answer)) {
            return '';
        }

        $columns = $this->getAnswerOptions();
        $lines = [];

        foreach ($this->getRows() as $row => $label) {
            $value = array_map(fn (mixed $column): string => $columns[$column] ?? (string) $column, (array) ($answer[$row] ?? []));

            if ($value !== []) {
                $lines[] = "{$label}: " . implode(', ', $value);
            }
        }

        return implode('; ', $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            'rows' => static::optionsToDefinition($this->evaluate($this->rows)),
            'answers' => static::optionsToDefinition($this->evaluate($this->answers)),
            'multiple' => $this->evaluate($this->isMultiple) ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        if (isset($settings['rows'])) {
            $this->rows(static::optionsFromDefinition($settings['rows']));
        }

        if (isset($settings['answers'])) {
            $this->answers(static::optionsFromDefinition($settings['answers']));
        }

        $this->multiple((bool) ($settings['multiple'] ?? false));

        return $this;
    }
}
