<?php

namespace JaOcero\ConversationalForms\Components;

use Filament\Schemas\Components\Component;

/**
 * The step every plain child of a `Conversation` is wrapped in automatically.
 */
class ConversationStep extends Step
{
    protected string $view = 'conversational-forms::schemas.step';

    /**
     * @param  array<Component>  $components
     */
    final public function __construct(array $components = [], ?string $name = null)
    {
        $this->name = $name;
        $this->schema($components);
    }

    /**
     * @param  array<Component>  $components
     */
    public static function make(array $components = [], ?string $name = null): static
    {
        $static = app(static::class, ['components' => $components, 'name' => $name]);
        $static->configure();

        return $static;
    }
}
