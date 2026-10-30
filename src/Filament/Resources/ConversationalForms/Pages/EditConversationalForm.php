<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\Factory;
use JaOcero\ConversationalForms\Builder\BuilderSchema;
use JaOcero\ConversationalForms\Builder\DefinitionState;
use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Definitions\Exceptions\InvalidDefinitionException;
use JaOcero\ConversationalForms\Enums\FormStatus;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Schemas\ConversationalFormForm;
use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Support\QrCode;

/**
 * The builder. It edits the draft; the public page keeps showing the last
 * published version until the draft is published.
 *
 * @property-read ConversationalForm $record
 */
class EditConversationalForm extends EditRecord
{
    protected static string $resource = ConversationalFormResource::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedPencilSquare;

    public static function getNavigationLabel(): string
    {
        return static::text('builder');
    }

    protected Width | string | null $maxContentWidth = Width::SevenExtraLarge;

    /**
     * Set when the last save stopped because the draft can't be built.
     */
    protected bool $hasInvalidDraft = false;

    public function form(Schema $schema): Schema
    {
        $builder = new BuilderSchema('data');

        return $schema->components([
            Tabs::make()
                ->tabs([
                    Tab::make(static::text('tab_questions'))
                        ->icon(Heroicon::OutlinedQueueList)
                        ->schema([$builder->steps()]),
                    Tab::make(static::text('tab_screens'))
                        ->icon(Heroicon::OutlinedWindow)
                        ->schema($builder->welcomeAndEndings()),
                    Tab::make(static::text('tab_settings'))
                        ->icon(Heroicon::OutlinedCog6Tooth)
                        ->schema([
                            ...ConversationalFormForm::components(),
                            ...$builder->settings(),
                        ]),
                ])
                ->persistTabInQueryString()
                ->columnSpanFull(),
        ]);
    }

    public function getSubheading(): string | Htmlable | null
    {
        $version = $this->record->publishedVersion;

        return match (true) {
            $version === null => static::text('subheading_draft'),
            $this->record->hasUnpublishedChanges() => static::text('subheading_unpublished', ['version' => $version->number]),
            default => static::text('subheading_published', ['version' => $version->number]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            'title' => $data['title'] ?? null,
            'slug' => $data['slug'] ?? null,
            ...DefinitionState::fromDefinition($this->record->definition ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $definition = DefinitionState::toDefinition($data);

        try {
            ConversationDefinition::make($definition)->validate();
        } catch (InvalidDefinitionException | InvalidLogicException $exception) {
            Notification::make()
                ->danger()
                ->title(static::text('invalid_title'))
                ->body($exception->getMessage())
                ->persistent()
                ->send();

            $this->hasInvalidDraft = true;

            $this->halt();
        }

        return [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'definition' => $definition,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(static::text('preview'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->mountUsing(function (Action $action): void {
                    if (! $this->saveQuietly()) {
                        $action->cancel();
                    }
                })
                ->modalHeading(fn (): string => static::text('preview_heading', ['title' => $this->record->title]))
                ->modalDescription(static::text('preview_description'))
                ->modalContent(fn (): Factory | \Illuminate\Contracts\View\View => view('conversational-forms::builder.preview', [
                    'url' => ConversationalFormsServiceProvider::getFormUrl($this->record, preview: true),
                ]))
                ->modalWidth(Width::SevenExtraLarge)
                ->slideOver()
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(static::text('close_preview')),
            Action::make('publish')
                ->label(static::text('publish'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->requiresConfirmation()
                ->modalDescription(static::text('publish_description'))
                ->visible(fn (): bool => $this->record->hasUnpublishedChanges() || ($this->record->status !== FormStatus::Published))
                ->action(function (): void {
                    if (! $this->saveQuietly()) {
                        return;
                    }

                    try {
                        $version = $this->record->publish();
                    } catch (InvalidDefinitionException | InvalidLogicException $exception) {
                        Notification::make()->danger()->title(static::text('invalid_title'))->body($exception->getMessage())->persistent()->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title(static::text('published_title', ['version' => $version->number]))
                        ->body(static::text('published_body'))
                        ->actions([
                            Action::make('open')
                                ->label(static::text('open'))
                                ->url(ConversationalFormsServiceProvider::getFormUrl($this->record), shouldOpenInNewTab: true),
                        ])
                        ->send();
                }),
            Action::make('share')
                ->label(static::text('share'))
                ->icon(Heroicon::OutlinedShare)
                ->color('gray')
                ->visible(fn (): bool => $this->record->isPublished())
                ->modalHeading(static::text('share'))
                ->modalDescription(static::text('share_description'))
                ->modalContent(fn (): Factory | \Illuminate\Contracts\View\View => view('conversational-forms::builder.share', [
                    'url' => $url = ConversationalFormsServiceProvider::getFormUrl($this->record),
                    'title' => $this->record->title,
                    'slug' => $this->record->slug,
                    'scriptUrl' => route('conversational-forms.embed-script'),
                    'qrCode' => QrCode::svg($url),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(static::text('close_preview')),
            ActionGroup::make([
                Action::make('open')
                    ->label(static::text('open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (): string => ConversationalFormsServiceProvider::getFormUrl($this->record), shouldOpenInNewTab: true)
                    ->visible(fn (): bool => $this->record->isPublished()),
                Action::make('close')
                    ->label(static::text('close_form'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->requiresConfirmation()
                    ->modalDescription(static::text('close_form_description'))
                    ->visible(fn (): bool => $this->record->status === FormStatus::Published)
                    ->action(function (): void {
                        $this->record->close();

                        Notification::make()->success()->title(static::text('closed_title'))->send();
                    }),
                Action::make('reopen')
                    ->label(static::text('reopen_form'))
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->visible(fn (): bool => $this->record->status === FormStatus::Closed)
                    ->action(function (): void {
                        $this->record->reopen();

                        Notification::make()->success()->title(static::text('reopened_title'))->send();
                    }),
                DeleteAction::make(),
            ]),
        ];
    }

    /**
     * Save the draft without a notification. Returns whether it was saved
     * (an invalid draft shows why and isn't saved).
     */
    protected function saveQuietly(): bool
    {
        $this->hasInvalidDraft = false;

        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        return ! $this->hasInvalidDraft;
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    protected static function text(string $key, array $replace = []): string
    {
        return __("conversational-forms::conversational-forms.builder.form.{$key}", $replace);
    }
}
