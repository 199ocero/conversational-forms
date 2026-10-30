<?php

namespace JaOcero\ConversationalForms\Support;

/**
 * Names of the package's views. The view namespace is only registered once
 * the app boots, so static analysis can't check them on its own.
 */
final class Views
{
    /**
     * @return view-string
     */
    public static function name(string $view): string
    {
        /** @var view-string $name */
        $name = "conversational-forms::{$view}";

        return $name;
    }
}
