<?php

namespace JaOcero\ConversationalForms\Questions;

use Filament\Forms\Components\Concerns\HasOptions;
use JaOcero\ConversationalForms\Questions\Concerns\HasChoices;
use JaOcero\ConversationalForms\Support\KeyboardHint;

/**
 * A searchable list, for questions with many answers (countries, products).
 * Type to filter, use the arrow keys to move and Enter to choose.
 */
class Dropdown extends Question
{
    use HasChoices;
    use HasOptions;

    protected string $view = 'conversational-forms::questions.dropdown';

    public function shouldAutoAdvance(): bool
    {
        return true;
    }

    /**
     * @return list<array{value: mixed, label: string, key: string}>
     */
    public function getChoices(): array
    {
        $choices = [];

        foreach ($this->getOptions() as $value => $label) {
            $choices[] = ['value' => $value, 'label' => (string) $label, 'key' => ''];
        }

        return $choices;
    }

    /**
     * @return ?array<string>
     */
    public function getInValidationRuleValues(): ?array
    {
        return parent::getInValidationRuleValues() ?? array_map(strval(...), array_keys($this->getOptions()));
    }

    /**
     * @return array<KeyboardHint>
     */
    public function getKeyboardHints(): array
    {
        return [KeyboardHint::make('↑ ↓', __('conversational-forms::conversational-forms.hints.choose'))];
    }
}
