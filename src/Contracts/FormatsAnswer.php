<?php

namespace JaOcero\ConversationalForms\Contracts;

/**
 * Turns a stored answer into text for people, for example a choice's label
 * instead of its value. Used by answer piping, table columns and entries.
 */
interface FormatsAnswer
{
    public function formatAnswer(mixed $answer): string;
}
