<?php

namespace JaOcero\ConversationalForms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use JaOcero\ConversationalForms\Livewire\ConversationResult;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Models\ConversationalFormResponse;
use JaOcero\ConversationalForms\Models\ConversationalFormVersion;

/**
 * A builder-made conversation was completed on its public page. `$version`
 * and `$response` are `null` for previews of the draft, which are never
 * stored.
 */
class ConversationalFormSubmitted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public ConversationalForm $form,
        public ?ConversationalFormVersion $version,
        public array $data,
        public ConversationResult $result,
        public ?ConversationalFormResponse $response = null,
    ) {}

    public function isPreview(): bool
    {
        return ! $this->version instanceof ConversationalFormVersion;
    }
}
