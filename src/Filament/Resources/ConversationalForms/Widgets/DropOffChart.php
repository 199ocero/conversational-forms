<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Widgets;

use Filament\Widgets\ChartWidget;
use JaOcero\ConversationalForms\Analytics\FormAnalytics;
use JaOcero\ConversationalForms\Models\ConversationalForm;

/**
 * How many people answered each step, in order: where the bars drop is where
 * people stop.
 */
class DropOffChart extends ChartWidget
{
    public ?ConversationalForm $record = null;

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return __('conversational-forms::conversational-forms.responses.analytics.drop_off');
    }

    public function getDescription(): string
    {
        return __('conversational-forms::conversational-forms.responses.analytics.drop_off_description');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        if (! $this->record instanceof ConversationalForm) {
            return ['datasets' => [], 'labels' => []];
        }

        $analytics = FormAnalytics::for($this->record);

        return [
            'datasets' => [
                [
                    'label' => __('conversational-forms::conversational-forms.responses.analytics.answered'),
                    'data' => array_values($analytics->answeredSteps),
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_keys($analytics->answeredSteps),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
