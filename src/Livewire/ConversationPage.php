<?php

namespace JaOcero\ConversationalForms\Livewire;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Livewire\Concerns\InteractsWithConversation;
use Livewire\Component;

/**
 * A full-screen, public conversation that works without a Filament panel or a
 * login. Register it with `Route::livewire('/apply', YourConversation::class)`.
 *
 * @property-read Schema $form
 */
abstract class ConversationPage extends Component implements HasSchemas
{
    use InteractsWithConversation;
    use InteractsWithSchemas;

    protected static ?string $title = null;

    protected static string $layout = 'conversational-forms::layouts.public';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    abstract public function conversation(Conversation $conversation): Conversation;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->conversation(Conversation::make()->fullScreen()),
            ]);
    }

    public function getTitle(): string
    {
        return static::$title ?? config('app.name');
    }

    public function render(): View
    {
        /** @var view-string $view The package's view namespace is only registered once the app boots. */
        $view = 'conversational-forms::livewire.conversation-page';

        return view($view)
            ->layout(static::$layout, [
                'title' => $this->getTitle(),
            ]);
    }
}
