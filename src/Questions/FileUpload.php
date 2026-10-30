<?php

namespace JaOcero\ConversationalForms\Questions;

use Filament\Forms\Components\FileUpload as BaseFileUpload;
use JaOcero\ConversationalForms\Contracts\ConversationQuestion;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Filament's file upload, with a conversational drop zone. Everything
 * Filament's `FileUpload` supports (disks, directories, accepted file types,
 * size limits, multiple files) works the same way.
 */
class FileUpload extends BaseFileUpload implements ConversationQuestion
{
    use Concerns\IsConversationQuestion;

    protected string $view = 'conversational-forms::questions.file-upload';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpConversationQuestion();
    }

    public function formatAnswer(mixed $answer): string
    {
        $files = is_array($answer) ? $answer : [$answer];

        return implode(', ', array_filter(array_map(
            fn (mixed $file): ?string => match (true) {
                $file instanceof TemporaryUploadedFile => $file->getClientOriginalName(),
                is_string($file) => basename($file),
                default => null,
            },
            $files,
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            'multiple' => $this->isMultiple() ?: null,
            'max_files' => $this->getMaxFiles(),
            'max_size' => $this->getMaxSize(),
            'accepted_types' => $this->getAcceptedFileTypes() ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        $this->multiple((bool) ($settings['multiple'] ?? false));

        if (array_key_exists('max_files', $settings)) {
            $this->maxFiles($settings['max_files']);
        }

        if (array_key_exists('max_size', $settings)) {
            $this->maxSize($settings['max_size']);
        }

        if (filled($settings['accepted_types'] ?? null)) {
            $this->acceptedFileTypes(array_values((array) $settings['accepted_types']));
        }

        return $this;
    }
}
