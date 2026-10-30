<?php

namespace JaOcero\ConversationalForms\Contracts;

use Illuminate\Contracts\Support\Htmlable;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Logic\ConditionGroup;
use JaOcero\ConversationalForms\Logic\Rule;

/**
 * A field designed for conversations. Implemented by `Question` and by
 * questions built on other Filament fields, through `IsConversationQuestion`.
 */
interface ConversationQuestion extends ConversationAware, FormatsAnswer
{
    public function getName(): string;

    public function getConversation(): ?Conversation;

    public function getQuestionNumber(): ?int;

    public function getPipedLabel(): string | Htmlable | null;

    public function getPipedDescription(): string | Htmlable | null;

    /**
     * @return array{url: string, alt: string}|null
     */
    public function getMedia(): ?array;

    /**
     * @return list<Rule>
     */
    public function getLogic(): array;

    public function getVisibilityConditions(): ?ConditionGroup;

    public function passesVisibilityConditions(): bool;

    public function getVisibilityConditionsJs(): ?string;

    /**
     * @return array<string, string>|null
     */
    public function getAnswerLabels(): ?array;

    /**
     * The question as plain data; see `ConversationDefinition`.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array;

    /**
     * @param  array<string, mixed>  $definition
     */
    public function fillFromDefinition(array $definition): static;

    /**
     * @return list<Rule>
     */
    public function getOwnLogic(): array;
}
