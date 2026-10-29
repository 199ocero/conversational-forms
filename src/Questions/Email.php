<?php

namespace JaOcero\ConversationalForms\Questions;

class Email extends ShortText
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('email');
        $this->autocomplete('email');
        $this->inputMode('email');
    }

    public function getInputType(): string
    {
        return 'email';
    }

    public function getPlaceholder(): string
    {
        return $this->evaluate($this->placeholder) ?? __('conversational-forms::conversational-forms.questions.email_placeholder');
    }
}
