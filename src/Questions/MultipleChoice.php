<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use Filament\Forms\Components\Concerns\HasOptions;
use JaOcero\ConversationalForms\Questions\Concerns\HasChoices;
use Random\Engine\Mt19937;
use Random\Randomizer;

class MultipleChoice extends Question
{
    use HasChoices;
    use HasOptions;

    protected string $view = 'conversational-forms::questions.multiple-choice';

    protected bool | Closure $isMultiple = false;

    protected bool | Closure $allowsOther = false;

    protected bool | Closure $isRandomized = false;

    protected int | Closure | null $minSelections = null;

    protected int | Closure | null $maxSelections = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (MultipleChoice $component): ?array => $component->isMultiple() ? [] : null);

        $this->rule('array', fn (MultipleChoice $component): bool => $component->isMultiple());
        $this->rule(fn (MultipleChoice $component): string => "min:{$component->getMinSelections()}", fn (MultipleChoice $component): bool => $component->isMultiple() && filled($component->getMinSelections()));
        $this->rule(fn (MultipleChoice $component): string => "max:{$component->getMaxSelections()}", fn (MultipleChoice $component): bool => $component->isMultiple() && filled($component->getMaxSelections()));
    }

    public function multiple(bool | Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    /**
     * Add an "Other" choice with a text input. The typed text is stored as the answer.
     */
    public function other(bool | Closure $condition = true): static
    {
        $this->allowsOther = $condition;

        return $this;
    }

    /**
     * Shuffle the choices. The order is stable for each respondent, so it
     * does not change between steps.
     */
    public function randomize(bool | Closure $condition = true): static
    {
        $this->isRandomized = $condition;

        return $this;
    }

    public function minSelections(int | Closure | null $count): static
    {
        $this->minSelections = $count;

        return $this;
    }

    public function maxSelections(int | Closure | null $count): static
    {
        $this->maxSelections = $count;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function allowsOther(): bool
    {
        return (bool) $this->evaluate($this->allowsOther);
    }

    public function isRandomized(): bool
    {
        return (bool) $this->evaluate($this->isRandomized);
    }

    public function getMinSelections(): ?int
    {
        return $this->evaluate($this->minSelections);
    }

    public function getMaxSelections(): ?int
    {
        return $this->evaluate($this->maxSelections);
    }

    public function shouldAutoAdvance(): bool
    {
        return ! $this->isMultiple();
    }

    /**
     * @return list<array{value: mixed, label: string, key: string}>
     */
    public function getChoices(): array
    {
        $options = $this->getOptions();
        $values = array_keys($options);

        if ($this->isRandomized()) {
            $seed = crc32($this->getLivewire()->getId() . $this->getStatePath());
            $values = (new Randomizer(new Mt19937($seed)))->shuffleArray($values);
        }

        $choices = [];

        foreach ($values as $index => $value) {
            $choices[] = [
                'value' => $value,
                'label' => (string) $options[$value],
                'key' => static::getLetter($index),
            ];
        }

        return $choices;
    }

    public function getOtherKey(): string
    {
        return static::getLetter(count($this->getOptions()));
    }

    /**
     * @return ?array<string>
     */
    public function getInValidationRuleValues(): ?array
    {
        $values = parent::getInValidationRuleValues();

        if ($values !== null) {
            return $values;
        }

        return $this->allowsOther() ? null : array_map(strval(...), array_keys($this->getOptions()));
    }

    public function hasInValidationOnMultipleValues(): bool
    {
        return $this->isMultiple();
    }

    protected static function getLetter(int $index): string
    {
        return ($index < 26) ? chr(97 + $index) : '';
    }
}
