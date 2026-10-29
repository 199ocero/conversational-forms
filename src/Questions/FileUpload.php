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
}
