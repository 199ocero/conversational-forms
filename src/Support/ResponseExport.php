<?php

namespace JaOcero\ConversationalForms\Support;

use Illuminate\Database\Eloquent\Builder;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Models\ConversationalFormResponse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Responses as a CSV or Excel file: one row per response, one column per
 * question (labels as headings, answers as people read them), then hidden
 * fields and variables. Written while streaming, so large exports don't
 * need memory or a queue.
 */
final readonly class ResponseExport
{
    /**
     * @param  Builder<ConversationalFormResponse>  $query
     */
    public function __construct(
        private ConversationalForm $form,
        private Builder $query,
    ) {}

    /**
     * @param  'csv'|'xlsx'  $format
     */
    public function download(string $format = 'csv'): StreamedResponse
    {
        $name = str($this->form->slug)->append('-responses-', now()->format('Y-m-d'), ".{$format}")->toString();

        return response()->streamDownload(function () use ($format): void {
            $writer = ($format === 'xlsx') ? new XlsxWriter : new CsvWriter;
            $writer->openToFile('php://output');

            foreach ($this->rows() as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
        }, $name, [
            'Content-Type' => ($format === 'xlsx') ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=utf-8',
        ]);
    }

    /**
     * The heading row, then one row per response.
     *
     * @return iterable<list<string|int|float|null>>
     */
    public function rows(): iterable
    {
        $definition = $this->form->publishedVersion?->getDefinition() ?? $this->form->getDraftDefinition();
        $questions = $definition->getQuestions();
        $hiddenFields = $definition->getHiddenFieldKeys();
        $variables = array_keys($definition->toArray()['variables'] ?? []);

        yield [
            __('conversational-forms::conversational-forms.responses.columns.started_at'),
            __('conversational-forms::conversational-forms.responses.columns.completed_at'),
            __('conversational-forms::conversational-forms.responses.columns.status'),
            __('conversational-forms::conversational-forms.responses.columns.ending'),
            __('conversational-forms::conversational-forms.responses.columns.version'),
            ...array_map(fn (string $key): string => ResponseTranscript::heading($questions[$key]->toDefinition()['label'] ?? $key), array_keys($questions)),
            ...$hiddenFields,
            ...array_map(fn (string $variable): string => "@{$variable}", $variables),
        ];

        foreach ($this->query->clone()->with(['version', 'form'])->orderBy('started_at')->lazy(200) as $response) {
            $answers = ResponseTranscript::for($response)->answers();

            yield [
                $response->started_at->toDateTimeString(),
                $response->completed_at?->toDateTimeString(),
                $response->status->getLabel(),
                $response->ending,
                $response->version?->number,
                ...array_map(fn (string $key): string => $answers[$key] ?? '', array_keys($questions)),
                ...array_map(fn (string $key): string => (string) ($response->answers[$key] ?? ''), $hiddenFields),
                ...array_map(fn (string $variable): mixed => is_scalar($response->variables[$variable] ?? null) ? $response->variables[$variable] : null, $variables),
            ];
        }
    }
}
