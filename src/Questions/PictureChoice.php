<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;

/**
 * Like `MultipleChoice`, with an image for each answer.
 */
class PictureChoice extends MultipleChoice
{
    protected string $view = 'conversational-forms::questions.picture-choice';

    /**
     * @var array<string, string> | Closure
     */
    protected array | Closure $images = [];

    protected bool | Closure $hasLabels = true;

    /**
     * Image URLs keyed by option value.
     *
     * @param  array<string, string> | Closure  $images
     */
    public function images(array | Closure $images): static
    {
        $this->images = $images;

        return $this;
    }

    /**
     * Hide the answer labels under the images. They remain available to
     * screen readers as the images' text alternatives.
     */
    public function hiddenLabels(bool | Closure $condition = true): static
    {
        $this->hasLabels = fn (PictureChoice $component): bool => ! $component->evaluate($condition);

        return $this;
    }

    public function hasLabels(): bool
    {
        return (bool) $this->evaluate($this->hasLabels);
    }

    public function getImage(mixed $value): ?string
    {
        return $this->evaluate($this->images)[$value] ?? null;
    }
}
