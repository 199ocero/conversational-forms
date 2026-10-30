<?php

namespace JaOcero\ConversationalForms\Support;

use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Models\ConversationalFormResponse;

/**
 * A response as questions and answers, using the version it was given to:
 * choices show their labels, dates follow the question's format, and
 * `{name}` in a question is filled in with the earlier answer.
 */
final readonly class ResponseTranscript
{
    public function __construct(
        private ConversationalFormResponse $response,
        private ConversationDefinition $definition,
    ) {}

    public static function for(ConversationalFormResponse $response): self
    {
        return new self($response, $response->version?->getDefinition() ?? $response->form->getDraftDefinition());
    }

    /**
     * @return list<array{key: string, question: string, answer: string}>
     */
    public function rows(): array
    {
        $answers = $this->response->answers ?? [];
        $rows = [];

        foreach ($this->definition->getQuestions() as $key => $question) {
            if (! array_key_exists($key, $answers)) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'question' => $this->pipe((string) ($question->toDefinition()['label'] ?? $key)),
                'answer' => $question->formatAnswer($answers[$key]),
            ];
        }

        foreach ($this->definition->getHiddenFieldKeys() as $key) {
            if (filled($answers[$key] ?? null)) {
                $rows[] = ['key' => $key, 'question' => $key, 'answer' => (string) $answers[$key]];
            }
        }

        return $rows;
    }

    /**
     * A question as a column heading: without markup, and with piped answers
     * (`{name}`), which differ per response, shown as "…".
     */
    public static function heading(mixed $label): string
    {
        return trim((string) preg_replace('/\{@?[A-Za-z]\w*\}/', '…', strip_tags((string) $label)));
    }

    /**
     * Answers formatted for a spreadsheet, keyed by question key.
     *
     * @return array<string, string>
     */
    public function answers(): array
    {
        return array_column($this->rows(), 'answer', 'key');
    }

    private function pipe(string $text): string
    {
        $questions = $this->definition->getQuestions();
        $answers = $this->response->answers ?? [];
        $variables = $this->response->variables ?? [];

        return (string) preg_replace_callback('/\{(@?)([A-Za-z]\w*)\}/', function (array $match) use ($questions, $answers, $variables): string {
            [, $isVariable, $key] = $match;

            if ($isVariable !== '') {
                return Answer::format($variables[$key] ?? '');
            }

            $answer = $answers[$key] ?? null;

            return isset($questions[$key]) ? $questions[$key]->formatAnswer($answer) : Answer::format($answer);
        }, strip_tags($text));
    }
}
