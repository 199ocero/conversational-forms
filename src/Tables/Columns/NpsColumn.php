<?php

namespace JaOcero\ConversationalForms\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use JaOcero\ConversationalForms\Questions\Nps;

/**
 * A Net Promoter Score badge: red for detractors (0–6), amber for passives
 * (7–8) and green for promoters (9–10).
 */
class NpsColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->badge();

        $this->color(fn (mixed $state): ?string => match (Nps::getCategory($state)) {
            Nps::PROMOTER => 'success',
            Nps::PASSIVE => 'warning',
            Nps::DETRACTOR => 'danger',
            default => null,
        });

        $this->tooltip(fn (mixed $state): ?string => filled($category = Nps::getCategory($state))
            ? __("conversational-forms::conversational-forms.nps.{$category}")
            : null);
    }
}
