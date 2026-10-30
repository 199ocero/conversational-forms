<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;

/**
 * An answer recorded in the browser with the microphone or camera, or
 * uploaded where recording isn't possible. Recordings go through the same
 * upload pipeline as `FileUpload`, so disks, visibility and validation work
 * the same way.
 *
 * The duration limit is applied while recording; the size limit (and the
 * file type) are also checked on the server.
 */
abstract class MediaAnswer extends FileUpload
{
    protected string $view = 'conversational-forms::questions.media';

    protected int | Closure $maxDuration = 120;

    protected bool | Closure $allowsUpload = true;

    /**
     * `audio` or `video`.
     */
    abstract public function getKind(): string;

    protected function setUp(): void
    {
        parent::setUp();

        $this->multiple(false);
    }

    /**
     * The longest recording, in seconds. Recording stops by itself then.
     */
    public function maxDuration(int | Closure $seconds): static
    {
        $this->maxDuration = $seconds;

        return $this;
    }

    /**
     * Offer to upload a file instead of recording (default). It is always
     * offered when the browser can't record.
     */
    public function allowUpload(bool | Closure $condition = true): static
    {
        $this->allowsUpload = $condition;

        return $this;
    }

    public function getMaxDuration(): int
    {
        return max(1, (int) $this->evaluate($this->maxDuration));
    }

    public function allowsUpload(): bool
    {
        return (bool) $this->evaluate($this->allowsUpload);
    }

    public function shouldAutoAdvance(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            'max_duration' => $this->evaluate($this->maxDuration),
            'max_size' => $this->getMaxSize(),
            'allow_upload' => $this->allowsUpload() ? null : false,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        if (isset($settings['max_duration'])) {
            $this->maxDuration((int) $settings['max_duration']);
        }

        if (isset($settings['max_size'])) {
            $this->maxSize((int) $settings['max_size']);
        }

        $this->allowUpload((bool) ($settings['allow_upload'] ?? true));

        return $this;
    }
}
