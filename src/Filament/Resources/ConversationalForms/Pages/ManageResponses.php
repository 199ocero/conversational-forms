<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use JaOcero\ConversationalForms\Contracts\ConversationQuestion;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Enums\ResponseStatus;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Widgets\DropOffChart;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Widgets\NpsStats;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Widgets\ResponseStats;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Models\ConversationalFormResponse;
use JaOcero\ConversationalForms\Questions\Nps;
use JaOcero\ConversationalForms\Support\ResponseExport;
use JaOcero\ConversationalForms\Support\ResponseTranscript;
use JaOcero\ConversationalForms\Support\Views;
use JaOcero\ConversationalForms\Tables\Columns\AnswerColumn;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The answers given to a conversation, with completion and drop-off
 * analytics, a transcript of each response and exports.
 *
 * @method ConversationalForm getOwnerRecord()
 */
class ManageResponses extends ManageRelatedRecords
{
    protected static string $resource = ConversationalFormResource::class;

    protected static string $relationship = 'responses';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected Width | string | null $maxContentWidth = Width::Full;

    public static function getNavigationLabel(): string
    {
        return static::text('title');
    }

    public function getTitle(): string | Htmlable
    {
        return static::text('heading', ['title' => $this->getOwnerRecord()->title]);
    }

    public function getBreadcrumb(): string
    {
        return static::text('title');
    }

    public function table(Table $table): Table
    {
        $definition = $this->getDefinition();

        return $table
            ->recordTitle(fn (ConversationalFormResponse $record): string => $record->started_at->toDayDateTimeString())
            ->columns([
                TextColumn::make('completed_at')
                    ->label(static::text('columns.completed_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder(static::text('not_completed'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(static::text('columns.status'))
                    ->badge(),
                ...$this->answerColumns($definition),
                TextColumn::make('ending')
                    ->label(static::text('columns.ending'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('duration')
                    ->label(static::text('columns.duration'))
                    ->state(fn (ConversationalFormResponse $record): ?int => $record->getDurationInSeconds())
                    ->formatStateUsing(fn (?int $state): string => ($state === null) ? '' : gmdate(($state >= 3600) ? 'G:i:s' : 'i:s', $state))
                    ->toggleable(),
                TextColumn::make('version.number')
                    ->label(static::text('columns.version'))
                    ->prefix('v')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')
                    ->label(static::text('columns.started_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(static::text('columns.status'))
                    ->options(ResponseStatus::class)
                    ->default(ResponseStatus::Completed->value),
                SelectFilter::make('ending')
                    ->label(static::text('columns.ending'))
                    ->options(array_combine($definition->getEndingKeys(), $definition->getEndingKeys())),
                SelectFilter::make('version')
                    ->label(static::text('columns.version'))
                    ->relationship('version', 'number', fn (Builder $query): Builder => $query->where('conversational_form_id', $this->getOwnerRecord()->getKey()))
                    ->getOptionLabelFromRecordUsing(fn ($record): string => "v{$record->number}"),
                Filter::make('started_at')
                    ->schema([
                        DatePicker::make('from')->label(static::text('from')),
                        DatePicker::make('until')->label(static::text('until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('started_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('started_at', '<=', $date))),
            ])
            ->headerActions([
                ActionGroup::make([
                    $this->exportAction('csv'),
                    $this->exportAction('xlsx'),
                ])
                    ->label(static::text('export'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->button()
                    ->color('gray'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(static::text('view'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->modalHeading(fn (ConversationalFormResponse $record): string => static::text('transcript_heading', ['date' => $record->started_at->toDayDateTimeString()]))
                    ->modalContent(fn (ConversationalFormResponse $record): View => view(Views::name('builder.transcript'), [
                        'response' => $record,
                        'rows' => ResponseTranscript::for($record)->rows(),
                    ]))
                    ->modalWidth(Width::TwoExtraLarge)
                    ->slideOver()
                    ->modalSubmitAction(false),
                DeleteAction::make(),
            ])
            ->recordAction('view')
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export')
                        ->label(static::text('export_selected'))
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->action(fn (Collection $records): StreamedResponse => (new ResponseExport(
                            $this->getOwnerRecord(),
                            $this->getOwnerRecord()->responses()->getQuery()->whereKey($records->modelKeys()),
                        ))->download())
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(static::text('empty_heading'))
            ->emptyStateDescription(static::text('empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedInbox);
    }

    protected function getHeaderWidgets(): array
    {
        $hasNps = collect($this->getDefinition()->getQuestions())->contains(fn (mixed $question): bool => $question instanceof Nps);

        return [
            ResponseStats::class,
            ...($hasNps ? [NpsStats::class] : []),
            DropOffChart::class,
        ];
    }

    public function getWidgetData(): array
    {
        return ['record' => $this->getOwnerRecord()];
    }

    protected function getDefinition(): ConversationDefinition
    {
        $form = $this->getOwnerRecord();

        return $form->publishedVersion?->getDefinition() ?? $form->getDraftDefinition();
    }

    /**
     * One column per question, from the published version. The first few
     * are shown; the rest can be switched on.
     *
     * @return list<AnswerColumn>
     */
    protected function answerColumns(ConversationDefinition $definition): array
    {
        $columns = [];

        foreach (array_values($definition->getQuestions()) as $index => $question) {
            /** @var ConversationQuestion $question */
            $key = $question->getName();

            $columns[] = AnswerColumn::make("answer_{$key}")
                ->label(str(ResponseTranscript::heading($question->toDefinition()['label'] ?? $key))->limit(40)->toString())
                ->state(fn (ConversationalFormResponse $record): mixed => $record->answers[$key] ?? null)
                ->question($question)
                ->limit(50)
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: $index >= 4);
        }

        return $columns;
    }

    /**
     * @param  'csv'|'xlsx'  $format
     */
    protected function exportAction(string $format): Action
    {
        return Action::make("export_{$format}")
            ->label(static::text("export_{$format}"))
            ->action(fn (): StreamedResponse => (new ResponseExport(
                $this->getOwnerRecord(),
                $this->getFilteredTableQuery() ?? $this->getOwnerRecord()->responses()->getQuery(),
            ))->download($format));
    }

    /**
     * @param  array<string, string>  $replace
     */
    protected static function text(string $key, array $replace = []): string
    {
        return __("conversational-forms::conversational-forms.responses.{$key}", $replace);
    }
}
