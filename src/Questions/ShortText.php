<?php

namespace JaOcero\ConversationalForms\Questions;

use Filament\Forms\Components\Concerns\CanBeAutocompleted;
use Filament\Forms\Components\Concerns\CanBeLengthConstrained;
use Filament\Forms\Components\Concerns\HasExtraInputAttributes;
use Filament\Forms\Components\Concerns\HasInputMode;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Contracts\CanBeLengthConstrained as CanBeLengthConstrainedContract;

class ShortText extends Question implements CanBeLengthConstrainedContract
{
    use CanBeAutocompleted;
    use CanBeLengthConstrained;
    use HasExtraInputAttributes;
    use HasInputMode;
    use HasPlaceholder;

    protected string $view = 'conversational-forms::questions.short-text';

    public function getInputType(): string
    {
        return 'text';
    }

    /**
     * A JavaScript regular expression the whole value must keep matching while
     * the respondent types or pastes. Characters that would break it are
     * never inserted. Validation still runs on the server.
     */
    public function getInputFilter(): ?string
    {
        return null;
    }

    public function getPlaceholder(): string
    {
        return $this->evaluate($this->placeholder) ?? __('conversational-forms::conversational-forms.questions.placeholder');
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            'placeholder' => $this->evaluate($this->placeholder),
            'min_length' => $this->evaluate($this->minLength),
            'max_length' => $this->evaluate($this->maxLength),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        if (array_key_exists('placeholder', $settings)) {
            $this->placeholder($settings['placeholder']);
        }

        if (array_key_exists('min_length', $settings)) {
            $this->minLength($settings['min_length']);
        }

        if (array_key_exists('max_length', $settings)) {
            $this->maxLength($settings['max_length']);
        }

        return $this;
    }
}
