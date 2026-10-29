<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A signature, drawn with a mouse, pen or finger, or typed. The browser sends
 * a PNG, which is checked and stored on a disk when the conversation is
 * submitted. The answer is the stored file's path.
 */
class Signature extends Question
{
    protected const DATA_URL_PREFIX = 'data:image/png;base64,';

    protected const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    protected string $view = 'conversational-forms::questions.signature';

    protected string | Closure | null $disk = null;

    protected string | Closure $directory = 'signatures';

    protected int | Closure $maxSize = 1024;

    protected bool | Closure $canBeTyped = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(fn (Signature $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            if (blank($value) || $component->isStoredSignature($value)) {
                return;
            }

            if ($component->decode($value) === null) {
                $fail(__('conversational-forms::conversational-forms.signature.invalid'));
            }
        });

        $this->dehydrateStateUsing(fn (Signature $component, mixed $state): ?string => $component->store($state));
    }

    /**
     * The disk to store signatures on. Defaults to Filament's default disk.
     */
    public function disk(string | Closure | null $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function directory(string | Closure $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * The largest accepted image, in kilobytes.
     */
    public function maxSize(int | Closure $kilobytes): static
    {
        $this->maxSize = $kilobytes;

        return $this;
    }

    /**
     * Offer typing the name instead of drawing (default). Typing is the
     * keyboard and screen reader friendly way to sign.
     */
    public function typing(bool | Closure $condition = true): static
    {
        $this->canBeTyped = $condition;

        return $this;
    }

    public function getDiskName(): string
    {
        return $this->evaluate($this->disk) ?? config('filament.default_filesystem_disk', config('filesystems.default'));
    }

    public function getDirectory(): string
    {
        return trim($this->evaluate($this->directory), '/');
    }

    public function getMaxSize(): int
    {
        return (int) $this->evaluate($this->maxSize);
    }

    public function canBeTyped(): bool
    {
        return (bool) $this->evaluate($this->canBeTyped);
    }

    /**
     * A path that is already stored (when editing), rather than a new drawing.
     */
    public function isStoredSignature(mixed $value): bool
    {
        return is_string($value)
            && (! str_starts_with($value, 'data:'))
            && str_starts_with($value, $this->getDirectory() . '/')
            && str_ends_with($value, '.png');
    }

    /**
     * The PNG bytes of a drawn signature, or `null` when the value is not a
     * PNG within the size limit.
     */
    public function decode(mixed $value): ?string
    {
        if ((! is_string($value)) || (! str_starts_with($value, static::DATA_URL_PREFIX))) {
            return null;
        }

        $encoded = substr($value, strlen(static::DATA_URL_PREFIX));

        if (strlen($encoded) > (int) ceil($this->getMaxSize() * 1024 * 4 / 3) + 4) {
            return null;
        }

        $binary = base64_decode($encoded, strict: true);

        if (($binary === false) || (! str_starts_with($binary, static::PNG_SIGNATURE))) {
            return null;
        }

        return $binary;
    }

    public function store(mixed $state): ?string
    {
        if (blank($state) || $this->isStoredSignature($state)) {
            return $state;
        }

        $binary = $this->decode($state);

        if ($binary === null) {
            return null;
        }

        $path = $this->getDirectory() . '/' . Str::ulid() . '.png';

        Storage::disk($this->getDiskName())->put($path, $binary);

        return $path;
    }

    public function formatAnswer(mixed $answer): string
    {
        return filled($answer) ? __('conversational-forms::conversational-forms.signature.signed') : '';
    }
}
