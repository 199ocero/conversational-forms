<?php

namespace JaOcero\ConversationalForms\Questions\Concerns;

use Closure;

trait HasMedia
{
    protected string | Closure | null $mediaUrl = null;

    protected string | Closure | null $mediaAlt = null;

    /**
     * An image shown with the question: beside it on wide screens, above the
     * answer on small ones. Describe the image in `$alt`, or pass an empty
     * string when it is purely decorative.
     */
    public function media(string | Closure | null $url, string | Closure | null $alt = ''): static
    {
        $this->mediaUrl = $url;
        $this->mediaAlt = $alt;

        return $this;
    }

    /**
     * @return array{url: string, alt: string}|null
     */
    public function getMedia(): ?array
    {
        $url = $this->evaluate($this->mediaUrl);

        if (blank($url)) {
            return null;
        }

        return ['url' => $url, 'alt' => (string) $this->evaluate($this->mediaAlt)];
    }
}
