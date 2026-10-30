<?php

namespace JaOcero\ConversationalForms\Questions;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use InvalidArgumentException;
use JaOcero\ConversationalForms\Questions\Concerns\HasSegmentDropdowns;

/**
 * A date typed into day, month and year boxes, each with a list to pick
 * from, plus a calendar. Saved as `YYYY-MM-DD`.
 */
class DateQuestion extends Question
{
    use HasSegmentDropdowns;

    public const FORMATS = ['DMY', 'MDY', 'YMD'];

    protected string $view = 'conversational-forms::questions.segments';

    protected string | Closure $format = 'DMY';

    protected string | DateTimeInterface | Closure | null $minDate = null;

    protected string | DateTimeInterface | Closure | null $maxDate = null;

    protected bool | Closure $hasCalendar = true;

    protected int | Closure | null $firstDayOfWeek = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('date_format:Y-m-d');
        $this->rule(fn (DateQuestion $component): string => "after_or_equal:{$component->getMinDate()}", fn (DateQuestion $component): bool => filled($component->getMinDate()));
        $this->rule(fn (DateQuestion $component): string => "before_or_equal:{$component->getMaxDate()}", fn (DateQuestion $component): bool => filled($component->getMaxDate()));

        $this->validationMessages([
            'date_format' => __('conversational-forms::conversational-forms.questions.invalid_date'),
            'after_or_equal' => __('conversational-forms::conversational-forms.questions.date_too_early'),
            'before_or_equal' => __('conversational-forms::conversational-forms.questions.date_too_late'),
        ]);
    }

    /**
     * The order of the boxes: `DMY` (default), `MDY` or `YMD`.
     */
    public function format(string | Closure $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function minDate(string | DateTimeInterface | Closure | null $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    public function maxDate(string | DateTimeInterface | Closure | null $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    /**
     * Show a button that opens a calendar under the boxes (default).
     */
    public function calendar(bool | Closure $condition = true): static
    {
        $this->hasCalendar = $condition;

        return $this;
    }

    /**
     * The calendar's first column: 0 for Sunday to 6 for Saturday. Defaults to
     * the app locale's convention (Monday for `en`, Sunday for `en_US`).
     */
    public function firstDayOfWeek(int | Closure | null $day): static
    {
        $this->firstDayOfWeek = $day;

        return $this;
    }

    public function getFormat(): string
    {
        $format = strtoupper($this->evaluate($this->format));

        if (! in_array($format, static::FORMATS, strict: true)) {
            throw new InvalidArgumentException('A date format must be one of [' . implode(', ', static::FORMATS) . "]; [{$format}] was given.");
        }

        return $format;
    }

    public function getMinDate(): ?string
    {
        return static::toDateString($this->evaluate($this->minDate));
    }

    public function getMaxDate(): ?string
    {
        return static::toDateString($this->evaluate($this->maxDate));
    }

    public function hasCalendar(): bool
    {
        return (bool) $this->evaluate($this->hasCalendar);
    }

    public function getFirstDayOfWeek(): int
    {
        $day = $this->evaluate($this->firstDayOfWeek)
            ?? CarbonImmutable::now()->locale(app()->getLocale())->getTranslationMessage('first_day_of_week');

        return is_numeric($day) ? ((int) $day) % 7 : 0;
    }

    /**
     * The years offered in the year list: from the minimum date's year (or a
     * hundred years ago) to the maximum date's year (or ten years ahead).
     *
     * @return array{int, int}
     */
    public function getYearRange(): array
    {
        $currentYear = CarbonImmutable::now()->year;
        $min = $this->getMinDate();
        $max = $this->getMaxDate();

        return [
            $min ? (int) substr($min, 0, 4) : $currentYear - 100,
            $max ? (int) substr($max, 0, 4) : $currentYear + 10,
        ];
    }

    public function getKind(): string
    {
        return 'date';
    }

    /**
     * @return list<array{key: string, length: int, label: string, placeholder: string, options: list<array{value: string, label: string}>}>
     */
    public function getSegments(): array
    {
        $hasDropdowns = $this->hasDropdowns();
        $locale = app()->getLocale();

        $segments = [
            'D' => [
                'key' => 'day',
                'length' => 2,
                'label' => __('conversational-forms::conversational-forms.questions.day'),
                'placeholder' => 'DD',
                'options' => $hasDropdowns ? static::segmentOptions(range(1, 31), 2) : [],
            ],
            'M' => [
                'key' => 'month',
                'length' => 2,
                'label' => __('conversational-forms::conversational-forms.questions.month'),
                'placeholder' => 'MM',
                'options' => $hasDropdowns ? static::segmentOptions(range(1, 12), 2, fn (int $month): string => CarbonImmutable::create(2000, $month)->locale($locale)->translatedFormat('F')) : [],
            ],
            'Y' => [
                'key' => 'year',
                'length' => 4,
                'label' => __('conversational-forms::conversational-forms.questions.year'),
                'placeholder' => 'YYYY',
                'options' => $hasDropdowns ? static::segmentOptions(range(...$this->getYearRange()), 4) : [],
            ],
        ];

        return array_map(fn (string $part): array => $segments[$part], str_split($this->getFormat()));
    }

    public function getSeparator(): string
    {
        return '/';
    }

    public function shouldAutoAdvance(): bool
    {
        return true;
    }

    public function formatAnswer(mixed $answer): string
    {
        if (! is_string($answer) || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $answer) !== 1)) {
            return '';
        }

        [$year, $month, $day] = explode('-', $answer);

        return match ($this->getFormat()) {
            'MDY' => "{$month}/{$day}/{$year}",
            'YMD' => "{$year}/{$month}/{$day}",
            default => "{$day}/{$month}/{$year}",
        };
    }

    protected static function toDateString(mixed $date): ?string
    {
        return match (true) {
            $date instanceof DateTimeInterface => $date->format('Y-m-d'),
            filled($date) => CarbonImmutable::parse($date)->format('Y-m-d'),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            'format' => $this->getFormat(),
            'min_date' => $this->getMinDate(),
            'max_date' => $this->getMaxDate(),
            'calendar' => $this->hasCalendar() ? null : false,
            'dropdowns' => $this->hasDropdowns() ? null : false,
            'first_day_of_week' => $this->evaluate($this->firstDayOfWeek),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        if (isset($settings['format'])) {
            $this->format((string) $settings['format']);
        }

        if (array_key_exists('min_date', $settings)) {
            $this->minDate($settings['min_date']);
        }

        if (array_key_exists('max_date', $settings)) {
            $this->maxDate($settings['max_date']);
        }

        $this->calendar((bool) ($settings['calendar'] ?? true));
        $this->dropdowns((bool) ($settings['dropdowns'] ?? true));

        if (array_key_exists('first_day_of_week', $settings)) {
            $this->firstDayOfWeek($settings['first_day_of_week']);
        }

        return $this;
    }
}
