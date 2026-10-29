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

    public function getPlaceholder(): string
    {
        return $this->evaluate($this->placeholder) ?? __('conversational-forms::conversational-forms.questions.placeholder');
    }
}
