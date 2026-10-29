<?php

namespace JaOcero\ConversationalForms\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use JaOcero\ConversationalForms\Concerns\FormatsAnswers;

/**
 * Shows an answer the way its question describes it: a choice's label instead
 * of its value, "Yes" / "No" instead of `true` / `false`.
 *
 *     AnswerColumn::make('plan')->question(MultipleChoice::make('plan')->options($plans))
 */
class AnswerColumn extends TextColumn
{
    use FormatsAnswers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatStateUsing(fn (AnswerColumn $column, mixed $state): string => $column->formatAnswer($state));
    }
}
