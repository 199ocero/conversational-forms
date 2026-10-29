<?php

namespace JaOcero\ConversationalForms\Screens;

class WelcomeScreen extends Screen
{
    protected string $view = 'conversational-forms::screens.welcome-screen';

    public function getButtonLabel(): string
    {
        return $this->evaluate($this->buttonLabel) ?? __('conversational-forms::conversational-forms.welcome_screen.start');
    }
}
