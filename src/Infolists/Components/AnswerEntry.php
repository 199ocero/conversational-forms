<?php

namespace JaOcero\ConversationalForms\Infolists\Components;

use Filament\Infolists\Components\TextEntry;
use JaOcero\ConversationalForms\Concerns\FormatsAnswers;

/**
 * Shows an answer the way its question describes it, in an infolist.
 *
 *     AnswerEntry::make('plan')->question(MultipleChoice::make('plan')->options($plans))
 */
class AnswerEntry extends TextEntry
{
    use FormatsAnswers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatStateUsing(fn (AnswerEntry $entry, mixed $state): string => $entry->formatAnswer($state));
    }
}
