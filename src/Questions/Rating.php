<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use InvalidArgumentException;
use JaOcero\ConversationalForms\Questions\Concerns\HasChoices;
use JaOcero\ConversationalForms\Questions\Concerns\HasCorrectAnswer;
use JaOcero\ConversationalForms\Support\KeyboardHint;

/**
 * A star (or heart, or thumbs up) rating. Saved as an integer from 1.
 */
class Rating extends Question
{
    use HasChoices;
    use HasCorrectAnswer;

    public const SHAPES = ['star', 'heart', 'thumb'];

    protected string $view = 'conversational-forms::questions.rating';

    protected int | Closure $steps = 5;

    protected string | Closure $shape = 'star';

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('integer');
        $this->rule(fn (Rating $component): string => "between:1,{$component->getSteps()}");
    }

    public function steps(int | Closure $steps): static
    {
        $this->steps = $steps;

        return $this;
    }

    public function shape(string | Closure $shape): static
    {
        $this->shape = $shape;

        return $this;
    }

    public function getSteps(): int
    {
        $steps = (int) $this->evaluate($this->steps);

        if (($steps < 3) || ($steps > 10)) {
            throw new InvalidArgumentException("A rating needs between 3 and 10 steps; [{$steps}] was given.");
        }

        return $steps;
    }

    public function getShape(): string
    {
        $shape = $this->evaluate($this->shape);

        if (! in_array($shape, static::SHAPES, strict: true)) {
            throw new InvalidArgumentException('A rating shape must be one of [' . implode(', ', static::SHAPES) . "]; [{$shape}] was given.");
        }

        return $shape;
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
        $steps = $this->getSteps();

        return array_map(
            fn (int $value): array => [
                'value' => $value,
                'label' => trans_choice('conversational-forms::conversational-forms.questions.rating_value', $value, ['value' => $value, 'max' => $steps]),
                'key' => (string) ($value % 10),
            ],
            range(1, $steps),
        );
    }

    public function formatAnswer(mixed $answer): string
    {
        return blank($answer) ? '' : "{$answer}/{$this->getSteps()}";
    }

    /**
     * @return array<KeyboardHint>
     */
    public function getKeyboardHints(): array
    {
        return [KeyboardHint::make("1–{$this->getSteps()}", __('conversational-forms::conversational-forms.hints.choose'))];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            'steps' => $this->evaluate($this->steps),
            'shape' => $this->evaluate($this->shape),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        if (isset($settings['steps'])) {
            $this->steps((int) $settings['steps']);
        }

        if (isset($settings['shape'])) {
            $this->shape((string) $settings['shape']);
        }

        return $this;
    }
}
