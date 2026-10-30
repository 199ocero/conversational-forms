<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use Filament\Forms\Components\Concerns\CanBeAccepted;

/**
 * A single tick box, for example "Keep me updated". Saved as `true` or `false`.
 * Use `accepted()` when it must be ticked to continue.
 */
class Checkbox extends Question
{
    use CanBeAccepted;

    protected string $view = 'conversational-forms::questions.checkbox';

    protected string | Closure | null $checkboxLabel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(false);
        $this->rule('boolean');
        $this->validationMessages([
            'accepted' => __('conversational-forms::conversational-forms.questions.must_accept'),
        ]);
    }

    /**
     * The text next to the tick box. Defaults to "Yes".
     */
    public function checkboxLabel(string | Closure | null $label): static
    {
        $this->checkboxLabel = $label;

        return $this;
    }

    public function getCheckboxLabel(): string
    {
        return $this->evaluate($this->checkboxLabel) ?? __('conversational-forms::conversational-forms.questions.yes');
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            'checkbox_label' => $this->evaluate($this->checkboxLabel),
            'must_accept' => $this->hasDefinitionRule('accepted') ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        if (array_key_exists('checkbox_label', $settings)) {
            $this->checkboxLabel($settings['checkbox_label']);
        }

        if ($settings['must_accept'] ?? false) {
            $this->accepted();
        }

        return $this;
    }
}
