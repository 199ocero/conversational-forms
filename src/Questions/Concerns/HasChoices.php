<?php

namespace JaOcero\ConversationalForms\Questions\Concerns;

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
