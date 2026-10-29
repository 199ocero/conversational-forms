<?php

namespace JaOcero\ConversationalForms\Questions;

/**
 * Net Promoter Score: "How likely are you to recommend us?" on a 0–10 scale.
 */
class Nps extends OpinionScale
{
    public const DETRACTOR = 'detractor';

    public const PASSIVE = 'passive';

    public const PROMOTER = 'promoter';

    protected function setUp(): void
    {
        parent::setUp();

        $this->range(0, 10);
        $this->startLabel(fn (): string => __('conversational-forms::conversational-forms.questions.nps_start'));
        $this->endLabel(fn (): string => __('conversational-forms::conversational-forms.questions.nps_end'));
    }

    /**
     * 0–6 are detractors, 7–8 passives and 9–10 promoters.
     */
    public static function getCategory(mixed $score): ?string
    {
        if (! is_numeric($score)) {
            return null;
        }

        return match (true) {
            $score >= 9 => self::PROMOTER,
            $score >= 7 => self::PASSIVE,
            default => self::DETRACTOR,
        };
    }
}
