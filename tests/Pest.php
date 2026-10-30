<?php

use Filament\Schemas\Components\Component;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\TestConversation;
use JaOcero\ConversationalForms\Tests\TestCase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(TestCase::class)->in(__DIR__);

/**
 * @param  array<Component> | Component  $components
 */
function conversation(array | Component $components): Testable
{
    TestConversation::$components = fn (): array => is_array($components) ? $components : [$components];
    TestConversation::$submissions = [];

    return Livewire::test(TestConversation::class);
}

function conversationIn(Testable $livewire): Conversation
{
    return $livewire->instance()->form->getComponent('conversation');
}

/**
 * @param  array<string, mixed>  $definition
 */
function definitionConversation(array $definition): Testable
{
    return conversation(ConversationDefinition::make($definition)->toConversation());
}
