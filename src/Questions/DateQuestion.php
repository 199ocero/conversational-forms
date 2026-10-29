<?php

namespace JaOcero\ConversationalForms\Questions;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A date typed into day, month and year boxes, with an optional calendar.
 * Saved as `YYYY-MM-DD`.
 */
class DateQuestion extends Question
{
    public const FORMATS = ['DMY', 'MDY', 'YMD'];

    protected string $view = 'conversational-forms::questions.segments';

    protected string | Closure $format = 'DMY';

    protected string | DateTimeInterface | Closure | null $minDate = null;

    protected string | DateTimeInterface | Closure | null $maxDate = null;

    protected bool | Closure $hasCalendar = true;

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
     * Show a button that opens the browser's date picker (default).
     */
    public function calendar(bool | Closure $condition = true): static
    {
        $this->hasCalendar = $condition;

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

    public function getKind(): string
    {
        return 'date';
    }

    /**
     * @return list<array{key: string, length: int, label: string, placeholder: string}>
     */
    public function getSegments(): array
    {
        $segments = [
            'D' => ['key' => 'day', 'length' => 2, 'label' => __('conversational-forms::conversational-forms.questions.day'), 'placeholder' => 'DD'],
            'M' => ['key' => 'month', 'length' => 2, 'label' => __('conversational-forms::conversational-forms.questions.month'), 'placeholder' => 'MM'],
            'Y' => ['key' => 'year', 'length' => 4, 'label' => __('conversational-forms::conversational-forms.questions.year'), 'placeholder' => 'YYYY'],
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
}
