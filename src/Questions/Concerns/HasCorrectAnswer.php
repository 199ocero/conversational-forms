<?php

namespace JaOcero\ConversationalForms\Questions\Concerns;

use Closure;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Logic\Rule;

/**
 * Quiz shorthand: `correct('b')->points(10)` adds 10 to the `score` variable
 * when the answer is correct. For multiple answers, every correct answer and
 * no other must be chosen. Correct answers are only known to the server.
 */
trait HasCorrectAnswer
{
    protected mixed $correctAnswer = null;

    protected int | float | Closure $points = 1;

    protected string | Closure $scoreVariable = 'score';

    public function correct(mixed $answer): static
    {
        $this->correctAnswer = $answer;

        return $this;
    }

    public function points(int | float | Closure $points, string | Closure $variable = 'score'): static
    {
        $this->points = $points;
        $this->scoreVariable = $variable;

        return $this;
    }

    public function getCorrectAnswer(): mixed
    {
        return $this->evaluate($this->correctAnswer);
    }

    public function isCorrect(mixed $answer): bool
    {
        $correct = $this->getCorrectAnswer();

        return ($correct !== null) && $this->getCorrectRule()->matches([$this->getName() => $answer]);
    }

    /**
     * @return list<Rule>
     */
    protected function getScoringRules(): array
    {
        if ($this->getCorrectAnswer() === null) {
            return [];
        }

        return [$this->getCorrectRule()->add((string) $this->evaluate($this->scoreVariable), $this->evaluate($this->points))];
    }

    protected function getCorrectRule(): Rule
    {
        $name = $this->getName();
        $correct = $this->getCorrectAnswer();

        if (! is_array($correct)) {
            return Logic::when($name, 'is', $correct);
        }

        $others = array_values(array_diff(array_map(strval(...), array_keys($this->getAnswerLabels())), array_map(strval(...), $correct)));

        return Logic::when($name, 'includes_all', $correct)->and($name, 'excludes', $others);
    }

    /**
     * @return array{answer: mixed, points: int|float, variable: string}|null
     */
    public function getCorrectAnswerDefinition(): ?array
    {
        if ($this->correctAnswer === null) {
            return null;
        }

        return [
            'answer' => $this->correctAnswer,
            'points' => $this->evaluate($this->points),
            'variable' => $this->evaluate($this->scoreVariable),
        ];
    }

    /**
     * @param  array{answer?: mixed, points?: int|float, variable?: string}  $definition
     */
    public function fillCorrectAnswerFromDefinition(array $definition): static
    {
        $this->correct($definition['answer'] ?? null);
        $this->points($definition['points'] ?? 1, $definition['variable'] ?? 'score');

        return $this;
    }
}
