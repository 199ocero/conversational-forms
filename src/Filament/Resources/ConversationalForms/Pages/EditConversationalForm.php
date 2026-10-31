<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use JaOcero\ConversationalForms\Builder\BuilderSchema;
use JaOcero\ConversationalForms\Builder\DefinitionState;
use JaOcero\ConversationalForms\Builder\ThemeSchema;
use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Definitions\Exceptions\InvalidDefinitionException;
use JaOcero\ConversationalForms\Definitions\QuestionTypeRegistry;
use JaOcero\ConversationalForms\Enums\FormStatus;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Schemas\ConversationalFormForm;
use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Questions\CompositeQuestion;
use JaOcero\ConversationalForms\Support\ImageUrl;
use JaOcero\ConversationalForms\Support\QrCode;
use JaOcero\ConversationalForms\Support\Views;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

use function Filament\Support\generate_icon_html;

/**
 * The builder: a full-screen editor with an outline of the conversation, a
 * live preview drawn in the browser, and the settings of the selected item.
 * Edits stay in the browser until they're saved; the public page keeps
 * showing the last published version until the draft is published.
 *
 * @property-read ConversationalForm $record
 */
class EditConversationalForm extends EditRecord
{
    protected static string $resource = ConversationalFormResource::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected string $view = 'conversational-forms::builder.editor.page';

    protected ?bool $hasUnsavedDataChangesAlert = true;

    /**
     * Addresses of the images in the draft, keyed by their stored path (or a
     * new upload's Livewire name), so the preview can show them.
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $imageUrls = [];

    public static function getNavigationLabel(): string
    {
        return static::text('builder');
    }

    /**
     * Set when the last save stopped because the draft can't be built.
     */
    protected bool $hasInvalidDraft = false;

    /**
     * Set while the editor saves by itself: problems are shown quietly in
     * the editor rather than as notifications, and nothing is selected.
     */
    protected bool $isAutosaving = false;

    /**
     * The deepest state path the builder makes: a condition on a question in a group.
     */
    public const MAX_NESTING_DEPTH = 16;

    /**
     * The builder's state is deeper than Livewire's default limit for update
     * paths (10): `data.steps.{uuid}.data.questions.{uuid}.data.visibility.conditions.{uuid}.field`
     * has 11 levels. Raise it for this page's requests only, before their
     * updates are applied, unless the app has turned it off or set it higher.
     */
    public function boot(): void
    {
        $limit = config('livewire.payload.max_nesting_depth');

        if (($limit !== null) && ($limit < static::MAX_NESTING_DEPTH)) {
            config(['livewire.payload.max_nesting_depth' => static::MAX_NESTING_DEPTH]);
        }
    }

    public function getLayout(): string
    {
        return 'conversational-forms::builder.editor.layout';
    }

    public function getHeader(): View
    {
        return view(Views::name('builder.editor.header'));
    }

    /**
     * The links to the logic map and responses are in the editor's own header.
     *
     * @return array<NavigationItem | NavigationGroup>
     */
    public function getSubNavigation(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        $builder = new BuilderSchema('data');

        // One item's settings at a time, in a narrow column: one column, tightly spaced.
        return $schema->columns(1)->dense()->components([
            $builder->steps(),
            $builder->welcome(),
            $builder->endings(),
            ...ThemeSchema::components(),
            Group::make([
                Section::make(static::text('title_and_address'))
                    ->contained(false)
                    ->schema(ConversationalFormForm::components()),
                ...$builder->settings(),
            ])->extraAttributes(BuilderSchema::shownWhenSelected('settings')),
        ]);
    }

    /**
     * What the editor needs in the browser: the question types, the parts of
     * contact and address questions, and how to draw themes.
     *
     * @return array<string, mixed>
     */
    public function getEditorConfiguration(): array
    {
        $types = [];
        $composites = [];

        foreach (app(QuestionTypeRegistry::class)->all() as $name => $type) {
            $types[$name] = [
                'label' => $type->getLabel(),
                'group' => $type->getGroup(),
                'icon' => self::iconHtml($type->getIcon()),
                'hasPreview' => filled($type->getPreviewView()),
            ];

            $question = $type->makeQuestion('preview');

            if ($question instanceof CompositeQuestion) {
                $question->required();

                $composites[$name] = array_map(fn (string $part, array $definition): array => [
                    'key' => $part,
                    'label' => $definition['label'],
                    'width' => $definition['width'] ?? 'full',
                    'isRequiredByDefault' => $question->isPartRequired($part),
                ], array_keys($question->getAvailableParts()), $question->getAvailableParts());
            }
        }

        $types['group'] = ['label' => __('conversational-forms::conversational-forms.builder.types.group'), 'group' => 'layout', 'icon' => self::iconHtml(Heroicon::OutlinedRectangleGroup), 'hasPreview' => true];
        $types['statement'] = ['label' => __('conversational-forms::conversational-forms.builder.types.statement'), 'group' => 'layout', 'icon' => self::iconHtml(Heroicon::OutlinedChatBubbleBottomCenterText), 'hasPreview' => true];

        return [
            'types' => $types,
            'groups' => __('conversational-forms::conversational-forms.builder.groups'),
            'composites' => $composites,
            'theme' => ThemeSchema::browserOptions(),
            'autosaveSeconds' => config('conversational-forms.builder.autosave_seconds', 3),
            'endingIcons' => [
                'end_screen' => self::iconHtml(Heroicon::OutlinedFlag),
                'redirect' => self::iconHtml(Heroicon::OutlinedArrowTopRightOnSquare),
            ],
        ];
    }

    protected static function iconHtml(string | BackedEnum | null $icon): ?string
    {
        return generate_icon_html($icon)?->toHtml();
    }

    protected function afterFill(): void
    {
        $this->refreshImageUrls();
    }

    /**
     * Keep the preview's image addresses up to date after uploads.
     */
    public function dehydrate(): void
    {
        $this->refreshImageUrls();
    }

    protected function refreshImageUrls(): void
    {
        $urls = [];

        foreach ($this->collectImages($this->data ?? []) as $image) {
            if ($image instanceof TemporaryUploadedFile) {
                try {
                    $urls[$image->serializeForLivewireResponse()] = $image->temporaryUrl();
                } catch (Throwable) {
                    // Not an image that can be previewed yet.
                }

                continue;
            }

            if (is_string($image) && filled($image) && (($url = ImageUrl::resolve($image)) !== null)) {
                $urls[$image] = $url;
            }
        }

        $this->imageUrls = $urls;
    }

    /**
     * The images in the draft: question media, picture choices and the
     * theme's background.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<mixed>
     */
    protected function collectImages(array $data): array
    {
        $images = [];

        $add = function (mixed $value) use (&$images): void {
            foreach (is_array($value) ? $value : [$value] as $image) {
                $images[] = $image;
            }
        };

        $addQuestion = function (array $item) use ($add): void {
            $add($item['data']['media']['url'] ?? null);

            foreach ($item['data']['settings']['options'] ?? [] as $option) {
                $add($option['image'] ?? null);
            }
        };

        foreach ($data['steps'] ?? [] as $item) {
            $addQuestion($item);

            foreach ($item['data']['questions'] ?? [] as $question) {
                $addQuestion($question);
            }
        }

        $add($data['theme']['background_image'] ?? null);

        return array_values(array_filter($images, fn (mixed $image): bool => filled($image)));
    }

    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        // Settings are shown one item at a time: the editor selects the first item with an error
        // (marking it quietly when it saved by itself).
        $this->dispatch('cf-editor-invalid', paths: array_keys($exception->errors()), isAutosave: $this->isAutosaving);
    }

    /**
     * Save the draft without a notification, as the editor does by itself a
     * few seconds after the last change (see `builder.autosave_seconds`).
     */
    public function autosave(): void
    {
        $this->isAutosaving = true;

        try {
            $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
        } finally {
            $this->isAutosaving = false;
        }
    }

    protected function afterSave(): void
    {
        $this->dispatch('cf-editor-saved');
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
            'theme' => ThemeSchema::toState($this->record->theme),
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
            if ($this->isAutosaving) {
                $this->dispatch('cf-editor-autosave-failed', message: $exception->getMessage());

                $this->halt();
            }

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
            'theme' => ThemeSchema::fromState($data['theme'] ?? null),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(static::text('save'))
                ->icon(Heroicon::OutlinedCheck)
                ->color('gray')
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->save(shouldRedirect: false)),
            Action::make('preview')
                ->label(static::text('test_run'))
                ->icon(Heroicon::OutlinedPlay)
                ->color('gray')
                ->mountUsing(function (Action $action): void {
                    if (! $this->saveQuietly()) {
                        $action->cancel();
                    }
                })
                ->modalHeading(fn (): string => static::text('preview_heading', ['title' => $this->record->title]))
                ->modalDescription(static::text('preview_description'))
                ->modalContent(fn (): View => view(Views::name('builder.preview'), [
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
                ->modalContent(fn (): View => view(Views::name('builder.share'), [
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
