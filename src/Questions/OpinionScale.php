<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use InvalidArgumentException;
use JaOcero\ConversationalForms\Questions\Concerns\HasChoices;
use JaOcero\ConversationalForms\Support\KeyboardHint;

class OpinionScale extends Question
{
    use HasChoices;

    protected string $view = 'conversational-forms::questions.opinion-scale';

    protected int | Closure $start = 0;

    protected int | Closure $end = 10;

    protected string | Closure | null $startLabel = null;

    protected string | Closure | null $endLabel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('integer');
        $this->rule(fn (OpinionScale $component): string => "between:{$component->getStart()},{$component->getEnd()}");
    }

    /**
     * The lowest and highest values on the scale, for example `range(1, 5)`.
     */
    public function range(int | Closure $start, int | Closure $end): static
    {
        $this->start = $start;
        $this->end = $end;

        return $this;
    }

    public function startLabel(string | Closure | null $label): static
    {
        $this->startLabel = $label;

        return $this;
    }

    public function endLabel(string | Closure | null $label): static
    {
        $this->endLabel = $label;

        return $this;
    }

    public function getStart(): int
    {
        return (int) $this->evaluate($this->start);
    }

    public function getEnd(): int
    {
        return (int) $this->evaluate($this->end);
    }

    public function getStartLabel(): ?string
    {
        return $this->evaluate($this->startLabel);
    }

    public function getEndLabel(): ?string
    {
        return $this->evaluate($this->endLabel);
    }

    public function shouldAutoAdvance(): bool
    {
        return true;
    }

    /**
     * @return list<array{value: int, label: string, key: string}>
     */
    public function getChoices(): array
    {
        $start = $this->getStart();
        $end = $this->getEnd();

        if (($start < 0) || ($end <= $start) || (($end - $start) > 10)) {
            throw new InvalidArgumentException("An opinion scale needs between 2 and 11 whole, non-negative steps; [{$start}–{$end}] was given.");
        }

        return array_map(
            fn (int $value): array => ['value' => $value, 'label' => (string) $value, 'key' => (string) $value],
            range($start, $end),
        );
    }

    /**
     * @return array<KeyboardHint>
     */
    public function getKeyboardHints(): array
    {
        return [KeyboardHint::make("{$this->getStart()}–{$this->getEnd()}", __('conversational-forms::conversational-forms.hints.choose'))];
    }
}
