<?php

namespace JaOcero\ConversationalForms\Screens;

use Closure;

class EndScreen extends Screen
{
    protected string $view = 'conversational-forms::screens.end-screen';

    protected string | Closure | null $buttonUrl = null;

    public function getTitle(): string
    {
        return parent::getTitle() ?? __('conversational-forms::conversational-forms.end_screen.title');
    }

    /**
     * An optional button, for example linking back to your website.
     */
    public function button(string | Closure | null $label, string | Closure | null $url): static
    {
        $this->buttonLabel = $label;
        $this->buttonUrl = $url;

        return $this;
    }

    public function getButtonLabel(): ?string
    {
        return $this->evaluate($this->buttonLabel);
    }

    public function getButtonUrl(): ?string
    {
        return $this->evaluate($this->buttonUrl);
    }
}
