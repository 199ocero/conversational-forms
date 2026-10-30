<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Widgets;

use Carbon\CarbonInterval;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use JaOcero\ConversationalForms\Analytics\FormAnalytics;
use JaOcero\ConversationalForms\Models\ConversationalForm;

class ResponseStats extends StatsOverviewWidget
{
    public ?ConversationalForm $record = null;

    protected ?string $pollingInterval = null;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        if (! $this->record instanceof ConversationalForm) {
            return [];
        }

        $analytics = FormAnalytics::for($this->record);
        $rate = $analytics->completionRate();

        return [
            Stat::make(static::text('started'), number_format($analytics->started))
                ->description(static::text('started_description')),
            Stat::make(static::text('completed'), number_format($analytics->completed))
                ->description(static::text('partial_count', ['count' => number_format($analytics->started - $analytics->completed)])),
            Stat::make(static::text('completion_rate'), ($rate === null) ? '–' : "{$rate}%")
                ->color(match (true) {
                    $rate === null => 'gray',
                    $rate >= 60 => 'success',
                    $rate >= 30 => 'warning',
                    default => 'danger',
                }),
            Stat::make(static::text('average_time'), ($analytics->averageSeconds === null)
                ? '–'
                : CarbonInterval::seconds($analytics->averageSeconds)->cascade()->forHumans(['short' => true, 'parts' => 2]))
                ->description(static::text('average_time_description')),
        ];
    }

    /**
     * @param  array<string, string>  $replace
     */
    protected static function text(string $key, array $replace = []): string
    {
        return __("conversational-forms::conversational-forms.responses.analytics.{$key}", $replace);
    }
}
