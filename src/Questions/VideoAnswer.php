<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;

/**
 * A video answer, recorded with the camera and microphone (up to 1 minute
 * and 12 MB by default, which fits Livewire's default upload limit; raise
 * `livewire.temporary_file_upload.rules` for longer videos).
 */
class VideoAnswer extends MediaAnswer
{
    protected int | Closure $maxDuration = 60;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maxSize(12 * 1024);
        $this->acceptedFileTypes(['video/*']);
    }

    public function getKind(): string
    {
        return 'video';
    }
}
