<?php

namespace JaOcero\ConversationalForms\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Enums\FormStatus;
use JaOcero\ConversationalForms\Events\ConversationalFormSubmitted;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Models\ConversationalFormVersion;
use JaOcero\ConversationalForms\Support\Models;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * The public page of a conversation made in the builder, at `/f/{slug}` by
 * default. It shows the published version; a signed preview link (from the
 * builder) shows the draft instead.
 *
 * @property-read ConversationalForm $conversationalForm
 * @property-read ConversationalFormVersion|null $version
 */
class ConversationalFormPage extends ConversationPage
{
    #[Locked]
    public string $formId = '';

    /**
     * The published version being answered, or `null` for a preview of the draft.
     */
    #[Locked]
    public ?string $versionId = null;

    public function mount(?string $slug = null, ?Request $request = null): void
    {
        $request ??= request();
        $isPreview = $request->boolean('preview') && $request->hasValidSignature();

        $form = Models::form()::query()->where('slug', $slug)->firstOrFail();

        abort_unless($isPreview || ($form->published_version_id !== null), 404);

        $this->formId = $form->getKey();
        $this->versionId = $isPreview ? null : $form->published_version_id;

        if ($this->isClosed()) {
            return;
        }

        parent::mount();
    }

    #[Computed(persist: false)]
    public function conversationalForm(): ConversationalForm
    {
        return Models::form()::query()->findOrFail($this->formId);
    }

    #[Computed(persist: false)]
    public function version(): ?ConversationalFormVersion
    {
        return ($this->versionId === null) ? null : Models::version()::query()->findOrFail($this->versionId);
    }

    public function isPreview(): bool
    {
        return $this->versionId === null;
    }

    public function isClosed(): bool
    {
        return (! $this->isPreview()) && ($this->conversationalForm->status !== FormStatus::Published);
    }

    public function getDefinition(): ConversationDefinition
    {
        return $this->version?->getDefinition() ?? $this->conversationalForm->getDraftDefinition();
    }

    public function conversation(Conversation $conversation): Conversation
    {
        return $this->getDefinition()->toConversation($conversation);
    }

    public function getTitle(): string
    {
        return $this->conversationalForm->title;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleConversationSubmission(array $data, ConversationResult $result): void
    {
        abort_if($this->isClosed(), 403);

        ConversationalFormSubmitted::dispatch($this->conversationalForm, $this->version, $data, $result);
    }

    public function render(): View
    {
        if (! $this->isClosed()) {
            return parent::render();
        }

        /** @var view-string $view */
        $view = 'conversational-forms::livewire.closed-form';

        return view($view)->layout(static::$layout, ['title' => $this->getTitle()]);
    }
}
