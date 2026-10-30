<?php

namespace JaOcero\ConversationalForms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use JaOcero\ConversationalForms\Models\ConversationalForm;

/**
 * @extends Factory<ConversationalForm>
 */
class ConversationalFormFactory extends Factory
{
    protected $model = ConversationalForm::class;

    public function definition(): array
    {
        $title = Str::title($this->faker->words(3, asText: true));

        return [
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(4)),
            'definition' => [
                'schema_version' => 1,
                'steps' => [
                    ['type' => 'short_text', 'key' => 'name', 'label' => "What's your name?", 'required' => true],
                    ['type' => 'email', 'key' => 'email', 'label' => 'Nice to meet you, {name}. Your email?', 'required' => true],
                ],
                'endings' => [['key' => 'default', 'type' => 'end_screen', 'title' => 'Thanks, {name}!']],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function withDefinition(array $definition): static
    {
        return $this->state(['definition' => $definition]);
    }

    public function published(): static
    {
        return $this->afterCreating(fn (ConversationalForm $form) => $form->publish());
    }
}
