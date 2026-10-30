<?php

namespace JaOcero\ConversationalForms\Analytics;

use JaOcero\ConversationalForms\Enums\ResponseStatus;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Models\ConversationalFormResponse;
use JaOcero\ConversationalForms\Questions\Nps;

/**
 * Numbers about a conversation's responses: how many started and finished,
 * how long finishing took, where people stopped, how they ended, and NPS.
 *
 * Steps are counted from each response's answered steps, which partial
 * responses record as people go, so drop-off needs no extra tracking.
 */
final readonly class FormAnalytics
{
    /**
     * @param  array<string, int>  $answeredSteps  Responses that answered each step, by step key.
     * @param  array<string, int>  $endings  Completed responses by ending.
     * @param  array<string, array{promoters: int, passives: int, detractors: int}>  $nps  By question key.
     */
    public function __construct(
        public int $started,
        public int $completed,
        public ?int $averageSeconds,
        public array $answeredSteps,
        public array $endings,
        public array $nps,
    ) {}

    public static function for(ConversationalForm $form): self
    {
        $definition = $form->publishedVersion?->getDefinition() ?? $form->getDraftDefinition();
        $npsQuestions = array_keys(array_filter($definition->getQuestions(), fn (mixed $question): bool => $question instanceof Nps));

        $started = 0;
        $completed = 0;
        $durations = [];
        $steps = array_fill_keys($definition->getStepKeys(), 0);
        $endings = [];
        $nps = array_fill_keys($npsQuestions, ['promoters' => 0, 'passives' => 0, 'detractors' => 0]);

        $form->responses()
            ->select(['id', 'status', 'answers', 'steps', 'ending', 'started_at', 'completed_at'])
            ->orderBy('id')
            ->chunk(500, function (iterable $responses) use (&$started, &$completed, &$durations, &$steps, &$endings, &$nps, $npsQuestions): void {
                /** @var ConversationalFormResponse $response */
                foreach ($responses as $response) {
                    $started++;

                    foreach ($response->steps ?? [] as $step) {
                        if (array_key_exists($step, $steps)) {
                            $steps[$step]++;
                        }
                    }

                    if ($response->status !== ResponseStatus::Completed) {
                        continue;
                    }

                    $completed++;
                    $durations[] = $response->getDurationInSeconds() ?? 0;

                    if (filled($response->ending)) {
                        $endings[$response->ending] = ($endings[$response->ending] ?? 0) + 1;
                    }

                    foreach ($npsQuestions as $key) {
                        $score = $response->answers[$key] ?? null;

                        if (is_numeric($score)) {
                            $nps[$key][self::npsCategory((int) $score)]++;
                        }
                    }
                }
            });

        return new self(
            started: $started,
            completed: $completed,
            averageSeconds: ($durations === []) ? null : (int) round(array_sum($durations) / count($durations)),
            answeredSteps: $steps,
            endings: $endings,
            nps: $nps,
        );
    }

    /**
     * The share of started responses that were completed, from 0 to 100.
     */
    public function completionRate(): ?float
    {
        return ($this->started === 0) ? null : round($this->completed / $this->started * 100, 1);
    }

    /**
     * The Net Promoter Score of an NPS question: the share of promoters
     * minus the share of detractors, from -100 to 100.
     */
    public function npsScore(string $key): ?int
    {
        $counts = $this->nps[$key] ?? null;
        $total = ($counts === null) ? 0 : array_sum($counts);

        if ($total === 0) {
            return null;
        }

        return (int) round(($counts['promoters'] - $counts['detractors']) / $total * 100);
    }

    /**
     * @return 'promoters'|'passives'|'detractors'
     */
    public static function npsCategory(int $score): string
    {
        return match (true) {
            $score >= 9 => 'promoters',
            $score >= 7 => 'passives',
            default => 'detractors',
        };
    }
}
