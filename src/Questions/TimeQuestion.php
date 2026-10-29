<?php

namespace JaOcero\ConversationalForms\Questions;

/**
 * A time typed into hour and minute boxes (24-hour clock). Saved as `HH:MM`.
 */
class TimeQuestion extends Question
{
    protected string $view = 'conversational-forms::questions.segments';

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('date_format:H:i');

        $this->validationMessages([
            'date_format' => __('conversational-forms::conversational-forms.questions.invalid_time'),
        ]);
    }

    public function getKind(): string
    {
        return 'time';
    }

    /**
     * @return list<array{key: string, length: int, label: string, placeholder: string}>
     */
    public function getSegments(): array
    {
        return [
            ['key' => 'hour', 'length' => 2, 'label' => __('conversational-forms::conversational-forms.questions.hour'), 'placeholder' => 'HH'],
            ['key' => 'minute', 'length' => 2, 'label' => __('conversational-forms::conversational-forms.questions.minute'), 'placeholder' => 'MM'],
        ];
    }

    public function getSeparator(): string
    {
        return ':';
    }

    public function hasCalendar(): bool
    {
        return false;
    }

    public function getMinDate(): ?string
    {
        return null;
    }

    public function getMaxDate(): ?string
    {
        return null;
    }

    public function shouldAutoAdvance(): bool
    {
        return true;
    }
}
