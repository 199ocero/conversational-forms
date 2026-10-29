<?php

namespace JaOcero\ConversationalForms\Contracts;

use JaOcero\ConversationalForms\Support\KeyboardHint;

/**
 * Optional contract for any Filament field, including third-party fields, that
 * wants richer behaviour inside a conversation.
 *
 * A field that auto-advances must dispatch a bubbling `cf-answered` DOM event
 * from its root element once its answer is complete.
 */
interface ConversationAware
{
    public function shouldAutoAdvance(): bool;

    /**
     * @return array<KeyboardHint>
     */
    public function getKeyboardHints(): array;
}
