<?php

namespace JaOcero\ConversationalForms\Questions;

/**
 * A spoken answer, recorded with the microphone (up to 2 minutes and 5 MB by
 * default). Browsers record WebM / Opus, Safari MP4 / AAC.
 */
class AudioAnswer extends MediaAnswer
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->maxSize(5 * 1024);

        // Audio-only WebM is often detected as `video/webm`, and M4A as `video/mp4`.
        $this->acceptedFileTypes(['audio/*', 'video/webm', 'video/mp4']);
    }

    public function getKind(): string
    {
        return 'audio';
    }
}
