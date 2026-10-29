<?php

namespace JaOcero\ConversationalForms\Tests\Fixtures\Livewire;

use Closure;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use JaOcero\ConversationalForms\Livewire\Concerns\InteractsWithConversation;
use JaOcero\ConversationalForms\Livewire\ConversationResult;
use Livewire\Component;

/**
 * @property-read Schema $form
 */
class TestConversation extends Component implements HasSchemas
{
    use InteractsWithConversation;
    use InteractsWithSchemas;

    public static ?Closure $components = null;

    /**
     * @var list<array{data: array<string, mixed>, result: ConversationResult}>
     */
    public static array $submissions = [];

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components((static::$components)());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleConversationSubmission(array $data, ConversationResult $result): void
    {
        static::$submissions[] = ['data' => $data, 'result' => $result];
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
