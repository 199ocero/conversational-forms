<?php

namespace JaOcero\ConversationalForms\Livewire;

/**
 * What the server worked out when a conversation was submitted. It is
 * recomputed from the validated answers, never taken from the browser.
 */
final readonly class ConversationResult
{
    /**
     * @param  array<string, mixed>  $data  The validated, dehydrated answers.
     * @param  list<string>  $path  The steps the respondent went through.
     */
    public function __construct(
        public array $data,
        public array $path,
        public string $ending,
    ) {}
}
