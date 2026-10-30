<?php

namespace JaOcero\ConversationalForms\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FormStatus: string implements HasColor, HasLabel
{
    /** Being written; the public page isn't available. */
    case Draft = 'draft';

    /** Answerable at its public page, using the latest published version. */
    case Published = 'published';

    /** No longer answerable; the public page says so. */
    case Closed = 'closed';

    public function getLabel(): string
    {
        return __("conversational-forms::conversational-forms.builder.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Closed => 'warning',
        };
    }
}
