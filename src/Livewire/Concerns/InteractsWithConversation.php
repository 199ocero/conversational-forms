<?php

namespace JaOcero\ConversationalForms\Livewire\Concerns;

use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Livewire\ConversationResult;
use Livewire\Attributes\Locked;
use LogicException;

/**
 * Add to any Livewire component (including Filament pages) that renders a
 * `Conversation`. Override `handleConversationSubmission()` to save the answers.
 */
trait InteractsWithConversation
{
    #[Locked]
    public ?string $completedConversationEnding = null;

    public function submitConversation(): void
    {
        $conversation = $this->getConversation();

        try {
            $data = $this->getConversationSchema()->getState();
        } catch (ValidationException $exception) {
            $this->dispatch(
                'cf-conversation-invalid',
                key: $conversation->getKey(),
                step: $conversation->getStepNameForStatePath((string) array_key_first($exception->errors())),
            );

            throw $exception;
        }

        $result = new ConversationResult(
            data: $data,
            path: $conversation->resolvePath()->toArray(),
            ending: $conversation->getEnding(),
        );

        $this->handleConversationSubmission($data, $result);

        $this->completedConversationEnding = $result->ending;

        $this->dispatch('cf-conversation-completed', key: $conversation->getKey(), ending: $result->ending);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleConversationSubmission(array $data, ConversationResult $result): void {}

    public function isConversationCompleted(): bool
    {
        return $this->completedConversationEnding !== null;
    }

    /**
     * The name of the schema method that contains the conversation.
     */
    protected function getConversationSchemaName(): string
    {
        return 'form';
    }

    protected function getConversationSchema(): Schema
    {
        $schema = $this->getSchema($this->getConversationSchemaName());

        if (! $schema) {
            throw new LogicException("The [{$this->getConversationSchemaName()}] schema could not be found on [" . static::class . '].');
        }

        return $schema;
    }

    public function getConversation(): Conversation
    {
        $conversation = $this->getConversationSchema()->getComponent(
            fn (mixed $component): bool => $component instanceof Conversation,
            withHidden: true,
        );

        if (! $conversation instanceof Conversation) {
            throw new LogicException("The [{$this->getConversationSchemaName()}] schema on [" . static::class . '] does not contain a conversation.');
        }

        return $conversation;
    }
}
