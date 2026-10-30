<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;

/**
 * A phone number. It accepts common formats ("+63 917 123 4567",
 * "(02) 8123-4567") and saves the digits with an optional leading "+".
 * For stricter, per-country validation, add a rule such as `phone:PH` from
 * `propaganistas/laravel-phone`.
 */
class Phone extends ShortText
{
    public const PATTERN = '/^\+?[0-9][0-9\s().-]{5,24}$/';

    protected bool | Closure $shouldNormalize = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('regex:' . static::PATTERN);
        $this->autocomplete('tel');
        $this->inputMode('tel');

        $this->dehydrateStateUsing(fn (Phone $component, ?string $state): ?string => $component->shouldNormalize() ? static::normalizeNumber($state) : $state);
    }

    /**
     * Save the number without spaces, dashes and brackets (default).
     */
    public function normalize(bool | Closure $condition = true): static
    {
        $this->shouldNormalize = $condition;

        return $this;
    }

    public function shouldNormalize(): bool
    {
        return (bool) $this->evaluate($this->shouldNormalize);
    }

    public static function normalizeNumber(?string $number): ?string
    {
        if (blank($number)) {
            return null;
        }

        return preg_replace('/(?!^\+)[^0-9]/', '', trim($number));
    }

    public function getInputType(): string
    {
        return 'tel';
    }

    /**
     * Only characters that appear in phone numbers can be typed.
     */
    public function getInputFilter(): string
    {
        return '^\\+?[0-9\\s().-]*$';
    }

    public function getPlaceholder(): string
    {
        return $this->evaluate($this->placeholder) ?? __('conversational-forms::conversational-forms.questions.phone_placeholder');
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            ...parent::getDefinitionSettings(),
            'normalize' => $this->evaluate($this->shouldNormalize),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        parent::definitionSettings($settings);

        if (isset($settings['normalize'])) {
            $this->normalize((bool) $settings['normalize']);
        }

        return $this;
    }
}
