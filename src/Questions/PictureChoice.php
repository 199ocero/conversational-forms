<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use JaOcero\ConversationalForms\Support\ImageUrl;

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
        return ImageUrl::resolve($this->evaluate($this->images)[$value] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        $settings = parent::getDefinitionSettings();
        $images = $this->evaluate($this->images);

        // Each option carries its image, so options and images can't drift apart.
        $settings['options'] = array_map(
            fn (array $option): array => [...$option, 'image' => $images[$option['value']] ?? null],
            $settings['options'],
        );

        return [
            ...$settings,
            'hidden_labels' => $this->evaluate($this->hasLabels) ? null : true,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        parent::definitionSettings($settings);

        $images = [];

        foreach ($settings['options'] ?? [] as $option) {
            if (filled($option['image'] ?? null)) {
                $images[(string) $option['value']] = (string) $option['image'];
            }
        }

        $this->images($images);
        $this->hiddenLabels((bool) ($settings['hidden_labels'] ?? false));

        return $this;
    }
}
