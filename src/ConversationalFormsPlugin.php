<?php

namespace JaOcero\ConversationalForms;

use Filament\Contracts\Plugin;
use Filament\Panel;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;

/**
 * Adds the conversation builder to a panel:
 *
 *     ->plugin(ConversationalFormsPlugin::make()->navigationGroup('Forms'))
 *
 * Conversations made in the builder are answered at `/f/{slug}`, outside
 * the panel (see the `routes` config).
 */
class ConversationalFormsPlugin implements Plugin
{
    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    /**
     * @var class-string<ConversationalFormResource>
     */
    protected string $resource = ConversationalFormResource::class;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'conversational-forms';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([$this->resource]);
    }

    public function boot(Panel $panel): void {}

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    /**
     * Use your own resource, for example one extending the package's with
     * extra pages or a different policy.
     *
     * @param  class-string<ConversationalFormResource>  $resource
     */
    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }
}
