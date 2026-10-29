<?php

namespace JaOcero\ConversationalForms\Questions;

class Website extends ShortText
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('url');
        $this->autocomplete('url');
        $this->inputMode('url');
    }

    public function getInputType(): string
    {
        return 'url';
    }

    public function getPlaceholder(): string
    {
        return $this->evaluate($this->placeholder) ?? 'https://';
    }
}
