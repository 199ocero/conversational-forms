<?php

namespace JaOcero\ConversationalForms\Screens;

use Closure;

class EndScreen extends Screen
{
    protected string $view = 'conversational-forms::screens.end-screen';

    protected string | Closure | null $buttonUrl = null;

    protected string $name = 'default';

    /**
     * Give each end screen a name so logic can choose it with `endWith()`.
     */
    public static function make(string $name = 'default'): static
    {
        $static = app(static::class);
        $static->name = $name;
        $static->configure();

        return $static;
    }

    public function getName(): string
    {
        return $this->name;
    }

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

    /**
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return array_filter([
            'key' => $this->getName(),
            'type' => 'end_screen',
            ...parent::toDefinition(),
            'button_url' => $this->getButtonUrl(),
        ], filled(...));
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function fillFromDefinition(array $definition): static
    {
        parent::fillFromDefinition($definition);

        $this->buttonUrl = $definition['button_url'] ?? null;

        return $this;
    }
}
