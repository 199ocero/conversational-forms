<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use JaOcero\ConversationalForms\Analytics\FormAnalytics;
use JaOcero\ConversationalForms\Models\ConversationalForm;

/**
 * The Net Promoter Score of each NPS question.
 */
class NpsStats extends StatsOverviewWidget
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
        $stats = [];

        foreach ($analytics->nps as $key => $counts) {
            $score = $analytics->npsScore($key);

            $stats[] = Stat::make(
                __('conversational-forms::conversational-forms.responses.analytics.nps', ['question' => $key]),
                ($score === null) ? '–' : (($score > 0) ? "+{$score}" : (string) $score),
            )
                ->description(__('conversational-forms::conversational-forms.responses.analytics.nps_breakdown', array_map(strval(...), $counts)))
                ->color(match (true) {
                    $score === null => 'gray',
                    $score >= 30 => 'success',
                    $score >= 0 => 'warning',
                    default => 'danger',
                });
        }

        return $stats;
    }
}
