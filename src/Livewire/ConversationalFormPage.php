<?php

namespace JaOcero\ConversationalForms\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Contracts\CaptchaProvider;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Enums\FormStatus;
use JaOcero\ConversationalForms\Enums\ResponseStatus;
use JaOcero\ConversationalForms\Events\ConversationalFormSubmitted;
use JaOcero\ConversationalForms\Logic\ConversationPath;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Models\ConversationalFormResponse;
use JaOcero\ConversationalForms\Models\ConversationalFormVersion;
use JaOcero\ConversationalForms\Support\Captcha;
use JaOcero\ConversationalForms\Support\Models;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * The public page of a conversation made in the builder, at `/f/{slug}` by
 * default. It shows the published version; a signed preview link (from the
 * builder) shows the draft instead, and `?embed=1` shows it for an iframe.
 *
 * Answers are saved as a partial response after each step and completed on
 * submit. Previews are never stored.
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

    /**
     * The response saved so far, once the first step is answered.
     */
    #[Locked]
    public ?string $responseId = null;

    /**
     * When the page was opened, to spot submissions faster than a person could answer.
     */
    #[Locked]
    public int $openedAt = 0;

    #[Locked]
    public bool $isEmbedded = false;

    /**
     * A field people never see. Bots that fill in every field fill this one too.
     */
    public string $website = '';

    public ?string $captchaToken = null;

    public function mount(?string $slug = null, ?Request $request = null): void
    {
        $request ??= request();
        $isPreview = $this->isPreviewRequest($request);

        $form = Models::form()::query()->where('slug', $slug)->firstOrFail();

        abort_unless($isPreview || ($form->published_version_id !== null), 404);

        $this->formId = $form->getKey();
        $this->versionId = $isPreview ? null : $form->published_version_id;
        $this->isEmbedded = $request->boolean('embed');
        $this->openedAt = now()->getTimestamp();

        $this->limitRequests();

        if ($this->isClosed()) {
            return;
        }

        parent::mount();
    }

    /**
     * A preview of the draft needs the signed link made by the builder.
     */
    protected function isPreviewRequest(Request $request): bool
    {
        return $request->boolean('preview') && $request->hasValidSignature();
    }

    /**
     * Every request after the first one (each step, each upload, submitting)
     * counts towards the visitor's limit.
     */
    public function hydrate(): void
    {
        $this->limitRequests();
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
        return $this->getDefinition()
            ->toConversation($conversation)
            ->fullScreen(! $this->isEmbedded)
            ->afterAdvance(fn (string $step, ConversationPath $path) => $this->saveProgress($step, $path));
    }

    public function getTitle(): string
    {
        return $this->conversationalForm->title;
    }

    public function getCaptcha(): ?CaptchaProvider
    {
        return $this->isPreview() ? null : Captcha::provider();
    }

    public function submitConversation(): void
    {
        $captcha = $this->getCaptcha();

        if (($captcha instanceof CaptchaProvider) && (! $captcha->verify($this->captchaToken, request()->ip()))) {
            $this->addError('captcha', __('conversational-forms::conversational-forms.public.captcha_failed'));

            return;
        }

        $this->resetErrorBag('captcha');

        parent::submitConversation();
    }

    /**
     * Save the answers so far as a partial response, so drop-off can be measured.
     */
    protected function saveProgress(string $step, ConversationPath $path): void
    {
        if ($this->isPreview() || $this->isClosed() || (! config('conversational-forms.responses.store_partials', true))) {
            return;
        }

        $index = $path->indexOf($step);

        $this->saveResponse([
            'status' => ResponseStatus::Partial,
            'answers' => self::storableAnswers($this->data ?? []),
            'variables' => $path->variables,
            'steps' => array_slice($path->steps, 0, ($index ?? 0) + 1),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleConversationSubmission(array $data, ConversationResult $result): void
    {
        abort_if($this->isClosed(), 403);

        if ($this->isPreview()) {
            ConversationalFormSubmitted::dispatch($this->conversationalForm, null, $data, $result, null);

            return;
        }

        // Looks like a bot: show the end screen as usual, but keep nothing.
        if ($this->isLikelySpam()) {
            if ($this->responseId !== null) {
                Models::response()::query()->whereKey($this->responseId)->delete();
                $this->responseId = null;
            }

            return;
        }

        $response = $this->saveResponse([
            'status' => ResponseStatus::Completed,
            'answers' => $data,
            'variables' => $result->variables,
            'steps' => $result->path,
            'ending' => $result->ending,
            'completed_at' => now(),
        ]);

        ConversationalFormSubmitted::dispatch($this->conversationalForm, $this->version, $data, $result, $response);
    }

    protected function isLikelySpam(): bool
    {
        if (config('conversational-forms.spam.honeypot', true) && filled($this->website)) {
            return true;
        }

        return (now()->getTimestamp() - $this->openedAt) < (int) config('conversational-forms.spam.minimum_seconds', 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function saveResponse(array $attributes): ConversationalFormResponse
    {
        $response = ($this->responseId === null) ? null : Models::response()::query()->find($this->responseId);

        $response ??= Models::response()::query()->make([
            'conversational_form_id' => $this->formId,
            'conversational_form_version_id' => $this->versionId,
            'started_at' => now(),
            'meta' => array_filter(['embedded' => $this->isEmbedded ?: null]),
        ]);

        // A completed response is never turned back into a partial one.
        if ($response->exists && $response->isCompleted()) {
            return $response;
        }

        $response->fill($attributes)->save();

        $this->responseId = $response->getKey();

        return $response;
    }

    protected function limitRequests(): void
    {
        $limit = (int) config('conversational-forms.spam.requests_per_minute', 120);

        if ($limit <= 0) {
            return;
        }

        $key = 'conversational-forms:' . $this->formId . ':' . request()->ip();

        abort_if(RateLimiter::tooManyAttempts($key, $limit), 429);

        RateLimiter::hit($key, 60);
    }

    /**
     * Answers as they can be stored mid-conversation: files that are still
     * being uploaded are left out until the conversation is submitted.
     *
     * @param  array<array-key, mixed>  $answers
     * @return array<array-key, mixed>
     */
    protected static function storableAnswers(array $answers): array
    {
        $storable = [];

        foreach ($answers as $key => $value) {
            if (is_array($value)) {
                $storable[$key] = self::storableAnswers($value);
            } elseif (is_scalar($value) || ($value === null)) {
                $storable[$key] = $value;
            }
        }

        return $storable;
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = $this->isClosed() ? 'conversational-forms::livewire.closed-form' : 'conversational-forms::livewire.conversational-form-page';

        return view($view)->layout(static::$layout, [
            'title' => $this->getTitle(),
            'isEmbedded' => $this->isEmbedded,
        ]);
    }
}
