<?php

namespace JaOcero\ConversationalForms\Questions\Concerns;

use Closure;
use JaOcero\ConversationalForms\Support\ImageUrl;

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
        $media = $this->getMediaDefinition();

        if ($media === null) {
            return null;
        }

        return ['url' => (string) ImageUrl::resolve($media['url']), 'alt' => $media['alt']];
    }

    /**
     * The media as given: a URL, or the path of an image uploaded in the builder.
     *
     * @return array{url: string, alt: string}|null
     */
    public function getMediaDefinition(): ?array
    {
        $url = $this->evaluate($this->mediaUrl);

        if (blank($url)) {
            return null;
        }

        return ['url' => (string) $url, 'alt' => (string) $this->evaluate($this->mediaAlt)];
    }
}
