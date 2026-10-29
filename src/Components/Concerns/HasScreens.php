<?php

namespace JaOcero\ConversationalForms\Components\Concerns;

use Closure;
use Filament\Schemas\Components\Component;
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\WelcomeScreen;

/**
 * The welcome and end screens live in their own child schemas, so they get the
 * same utility injection (`$get`, `$record`, `$livewire`) as any other component.
 */
trait HasScreens
{
    public function welcomeScreen(WelcomeScreen | Closure | null $screen): static
    {
        $this->childComponents(($screen === null) ? [] : [$screen], 'welcome');

        return $this;
    }

    public function endScreen(EndScreen | Closure $screen): static
    {
        return $this->endScreens([$screen]);
    }

    /**
     * Several end screens, chosen by logic with `endWith('name')`. The first one
     * is shown when no rule chooses another.
     *
     * @param  array<EndScreen | Closure> | Closure  $screens
     */
    public function endScreens(array | Closure $screens): static
    {
        $this->childComponents($screens, 'end');

        return $this;
    }

    /**
     * @template TScreen of Component
     *
     * @param  class-string<TScreen>  $type
     * @return TScreen|null
     */
    protected function getScreen(string $schema, string $type): ?Component
    {
        foreach ($this->getChildSchema($schema)?->getComponents(withActions: false) ?? [] as $component) {
            if ($component instanceof $type) {
                return $component;
            }
        }

        return null;
    }
}
