<?php

namespace JaOcero\ConversationalForms\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ResponseStatus: string implements HasColor, HasLabel
{
    /** Started and saved after each step, but not submitted (yet). */
    case Partial = 'partial';

    case Completed = 'completed';

    public function getLabel(): string
    {
        return __("conversational-forms::conversational-forms.responses.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Partial => 'gray',
            self::Completed => 'success',
        };
    }
}
