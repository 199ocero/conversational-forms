<?php

namespace JaOcero\ConversationalForms\Questions;

use Carbon\CarbonImmutable;
use Closure;
use JaOcero\ConversationalForms\Questions\Concerns\HasSegmentDropdowns;

/**
 * A time typed into hour and minute boxes, each with a list to pick from
 * (minutes in steps of five; any minute can be typed). The clock follows the
 * app locale: 12-hour locales (`en`, `en_US`) add an AM / PM box, 24-hour
 * ones (`en_GB`, `fr`) don't. Always saved as `HH:MM` on a 24-hour clock.
 */
class TimeQuestion extends Question
{
    use HasSegmentDropdowns;

    protected string $view = 'conversational-forms::questions.segments';

    protected bool | Closure | null $isTwelveHour = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('date_format:H:i');

        $this->validationMessages([
            'date_format' => __('conversational-forms::conversational-forms.questions.invalid_time'),
        ]);
    }

    /**
     * Use a 12-hour clock with an AM / PM box (`true`) or a 24-hour clock
     * (`false`). Defaults to the app locale's convention. The answer is saved
     * as `HH:MM` on a 24-hour clock either way.
     */
    public function twelveHour(bool | Closure | null $condition = true): static
    {
        $this->isTwelveHour = $condition;

        return $this;
    }

    public function isTwelveHour(): bool
    {
        $isTwelveHour = $this->evaluate($this->isTwelveHour);

        if ($isTwelveHour !== null) {
            return (bool) $isTwelveHour;
        }

        // `h` is the 12-hour token in the locale's time format (`h:mm A`), `H` the 24-hour one (`HH:mm`).
        return str_contains($this->getLocaleTimeFormat(), 'h');
    }

    public function getKind(): string
    {
        return 'time';
    }

    /**
     * @return list<array{key: string, length: int, label: string, placeholder: string, options: list<array{value: string, label: string}>, separator?: string}>
     */
    public function getSegments(): array
    {
        $hasDropdowns = $this->hasDropdowns();
        $padded = fn (int $number): string => str_pad((string) $number, 2, '0', STR_PAD_LEFT);

        if (! $this->isTwelveHour()) {
            return [
                $this->hourSegment($hasDropdowns ? static::segmentOptions(range(0, 23), 2, $padded) : []),
                $this->minuteSegment($hasDropdowns ? static::segmentOptions(range(0, 55, 5), 2, $padded) : []),
            ];
        }

        $meridiems = $this->getMeridiemLabels();
        $meridiem = [
            'key' => 'meridiem',
            'length' => max(2, mb_strlen($meridiems['am']), mb_strlen($meridiems['pm'])),
            'label' => __('conversational-forms::conversational-forms.questions.meridiem'),
            'placeholder' => $meridiems['am'],
            // Picked rather than typed, so the list is always there.
            'options' => [
                ['value' => 'am', 'label' => $meridiems['am']],
                ['value' => 'pm', 'label' => $meridiems['pm']],
            ],
            'separator' => '',
        ];

        $hour = $this->hourSegment($hasDropdowns ? static::segmentOptions(range(1, 12), 2) : []);
        $minute = $this->minuteSegment($hasDropdowns ? static::segmentOptions(range(0, 55, 5), 2, $padded) : []);

        // Some locales put AM / PM first (Korean `A h:mm`).
        if (str_starts_with(ltrim($this->getLocaleTimeFormat()), 'A')) {
            return [$meridiem, [...$hour, 'separator' => ''], $minute];
        }

        return [$hour, $minute, $meridiem];
    }

    /**
     * The locale's words for AM and PM (`AM` / `PM`, `오전` / `오후`).
     *
     * @return array{am: string, pm: string}
     */
    public function getMeridiemLabels(): array
    {
        $locale = app()->getLocale();

        return [
            'am' => CarbonImmutable::create(2000, 1, 1, 9)->locale($locale)->isoFormat('A'),
            'pm' => CarbonImmutable::create(2000, 1, 1, 21)->locale($locale)->isoFormat('A'),
        ];
    }

    public function getSeparator(): string
    {
        return ':';
    }

    public function hasCalendar(): bool
    {
        return false;
    }

    public function getMinDate(): ?string
    {
        return null;
    }

    public function getMaxDate(): ?string
    {
        return null;
    }

    public function shouldAutoAdvance(): bool
    {
        return true;
    }

    /**
     * Shows the answer on the question's clock: `9:30 PM` or `21:30`.
     */
    public function formatAnswer(mixed $answer): string
    {
        if (! is_string($answer) || (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $answer) !== 1)) {
            return '';
        }

        $time = CarbonImmutable::createFromFormat('!H:i', $answer)->locale(app()->getLocale());

        return $this->isTwelveHour() ? $time->isoFormat('h:mm A') : $answer;
    }

    protected function getLocaleTimeFormat(): string
    {
        return (string) CarbonImmutable::now()->locale(app()->getLocale())->getIsoFormats()['LT'];
    }

    /**
     * @param  list<array{value: string, label: string}>  $options
     * @return array{key: string, length: int, label: string, placeholder: string, options: list<array{value: string, label: string}>}
     */
    protected function hourSegment(array $options): array
    {
        return ['key' => 'hour', 'length' => 2, 'label' => __('conversational-forms::conversational-forms.questions.hour'), 'placeholder' => 'HH', 'options' => $options];
    }

    /**
     * @param  list<array{value: string, label: string}>  $options
     * @return array{key: string, length: int, label: string, placeholder: string, options: list<array{value: string, label: string}>}
     */
    protected function minuteSegment(array $options): array
    {
        return ['key' => 'minute', 'length' => 2, 'label' => __('conversational-forms::conversational-forms.questions.minute'), 'placeholder' => 'MM', 'options' => $options];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            'twelve_hour' => $this->evaluate($this->isTwelveHour),
            'dropdowns' => $this->hasDropdowns() ? null : false,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        if (array_key_exists('twelve_hour', $settings)) {
            $this->twelveHour($settings['twelve_hour']);
        }

        $this->dropdowns((bool) ($settings['dropdowns'] ?? true));

        return $this;
    }
}
