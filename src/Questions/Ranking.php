<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use Filament\Forms\Components\Concerns\HasOptions;

/**
 * Put the options in order, by dragging or with the keyboard. Saved as the
 * option values in the chosen order. The options start in the given order,
 * which is already a valid answer.
 */
class Ranking extends Question
{
    use HasOptions;

    protected string $view = 'conversational-forms::questions.ranking';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (Ranking $component): array => array_map(strval(...), array_keys($component->getOptions())));
        $this->rule('array');
        $this->rule(fn (Ranking $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            $expected = array_map(strval(...), array_keys($component->getOptions()));
            $given = array_map(strval(...), is_array($value) ? array_values($value) : []);

            sort($expected);
            sort($given);

            if ($given !== $expected) {
                $fail(__('conversational-forms::conversational-forms.questions.invalid_ranking'));
            }
        });
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function getItems(): array
    {
        $items = [];

        foreach ($this->getOptions() as $value => $label) {
            $items[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    public function getAnswerLabels(): array
    {
        return array_column($this->getItems(), 'label', 'value');
    }

    public function formatAnswer(mixed $answer): string
    {
        if (! is_array($answer)) {
            return '';
        }

        $labels = $this->getAnswerLabels();

        return implode(', ', array_map(
            fn (int $index, mixed $value): string => ($index + 1) . '. ' . ($labels[(string) $value] ?? (string) $value),
            array_keys(array_values($answer)),
            array_values($answer),
        ));
    }
}
