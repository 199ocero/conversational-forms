<?php

namespace JaOcero\ConversationalForms\Testing;

use Closure;
use Illuminate\Testing\Assert;
use JaOcero\ConversationalForms\Components\Conversation;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;

/**
 * Testing helpers, mixed into Livewire's `Testable`:
 *
 *     Livewire::test(Onboarding::class)
 *         ->set('data.name', 'Jay')
 *         ->advanceConversation('name')
 *         ->assertConversationPath(['name', 'plan', 'recommend'])
 *         ->submitConversation()
 *         ->assertConversationCompleted();
 *
 * Closures are bound to the `Testable` instance by `Testable::mixin()`.
 *
 * @mixin Testable<Component>
 */
class TestsConversations
{
    public function advanceConversation(): Closure
    {
        return function (string $fromStep, string $schema = 'form'): static {
            $conversation = $this->instance()->getSchema($schema)?->getComponent(
                fn (mixed $component): bool => $component instanceof Conversation,
                withHidden: true,
            );

            Assert::assertInstanceOf(Conversation::class, $conversation, "The [{$schema}] schema does not contain a conversation.");

            return $this->call('callSchemaComponentMethod', $conversation->getKey(), 'advance', ['fromStep' => $fromStep]);
        };
    }

    public function assertConversationPath(): Closure
    {
        return function (array $steps, string $schema = 'form'): static {
            $conversation = $this->instance()->getSchema($schema)?->getComponent(
                fn (mixed $component): bool => $component instanceof Conversation,
                withHidden: true,
            );

            Assert::assertInstanceOf(Conversation::class, $conversation, "The [{$schema}] schema does not contain a conversation.");
            Assert::assertSame($steps, $conversation->resolvePath()->toArray(), 'The conversation path does not match.');

            return $this;
        };
    }

    public function assertConversationAdvancedTo(): Closure
    {
        return fn (?string $step): static => $this->assertReturned(fn (mixed $response): bool => is_array($response) && ($response['next'] === $step));
    }

    public function submitConversation(): Closure
    {
        return fn (): static => $this->call('submitConversation');
    }

    public function assertConversationCompleted(): Closure
    {
        return function (?string $ending = null): static {
            $this->assertHasNoErrors();
            $this->assertDispatched('cf-conversation-completed');

            Assert::assertNotNull($this->get('completedConversationEnding'), 'The conversation was not completed.');

            if ($ending !== null) {
                Assert::assertSame($ending, $this->get('completedConversationEnding'));
            }

            return $this;
        };
    }
}
