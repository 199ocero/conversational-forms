<?php

namespace JaOcero\ConversationalForms\Questions\Concerns;

use JaOcero\ConversationalForms\Support\Answer;
use JaOcero\ConversationalForms\Support\KeyboardHint;

/**
 * Shared by questions that render a list of selectable answers.
 */
trait HasChoices
{
    /**
     * @return list<array{value: mixed, label: string, key: string}>
     */
    abstract public function getChoices(): array;

    public function formatAnswer(mixed $answer): string
    {
        $labels = $this->getAnswerLabels();

        $format = fn (mixed $value): string => $labels[static::getAnswerKey($value)] ?? Answer::format($value);

        return is_array($answer)
            ? implode(', ', array_filter(array_map($format, $answer), filled(...)))
            : (($answer === null) ? '' : $format($answer));
    }

    /**
     * @return array<string, string>
     */
    public function getAnswerLabels(): array
    {
        $labels = [];

        foreach ($this->getChoices() as $choice) {
            $labels[static::getAnswerKey($choice['value'])] = $choice['label'];
        }

        return $labels;
    }

    /**
     * Booleans become "1" and "0", matching how the browser looks labels up.
     */
    protected static function getAnswerKey(mixed $value): string
    {
        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    public function isMultiple(): bool
    {
        return false;
    }

    public function allowsOther(): bool
    {
        return false;
    }

    /**
     * @return array<KeyboardHint>
     */
    public function getKeyboardHints(): array
    {
        $choices = $this->getChoices();

        if ($choices === []) {
            return [];
        }

        $keys = strtoupper($choices[0]['key']) . ((count($choices) > 1) ? '–' . strtoupper($choices[array_key_last($choices)]['key']) : '');

        return [KeyboardHint::make($keys, __('conversational-forms::conversational-forms.hints.choose'))];
    }
}
