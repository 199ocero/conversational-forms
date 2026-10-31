<?php

namespace JaOcero\ConversationalForms\Builder;

use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Definitions\QuestionType;
use JaOcero\ConversationalForms\Definitions\QuestionTypeRegistry;
use JaOcero\ConversationalForms\Logic\Enums\AnswerType;
use JaOcero\ConversationalForms\Logic\Enums\CalculationOperator;
use JaOcero\ConversationalForms\Logic\Enums\Operator;

/**
 * The builder's form components. They edit the state made by
 * `DefinitionState`, at `$statePath` (the page's `data`), so pickers such as
 * "jump to" can list the steps currently in the builder.
 */
final readonly class BuilderSchema
{
    /**
     * Question types whose correct answer can be scored (`correct()`).
     */
    private const QUIZ_TYPES = ['multiple_choice', 'picture_choice', 'dropdown', 'yes_no', 'rating', 'opinion_scale', 'nps'];

    public function __construct(private string $statePath = 'data') {}

    /**
     * The editor's path for a state path: `data.steps.{uuid}.data.questions`
     * becomes `steps.{uuid}.questions`. The editor selects items by path.
     */
    public static function editorPath(string $statePath, string $root = 'data'): string
    {
        $segments = explode('.', $statePath);

        if ($segments[0] === $root) {
            array_shift($segments);
        }

        return implode('.', array_filter($segments, fn (string $segment): bool => $segment !== 'data'));
    }

    /**
     * Attributes that show a part of the editor's settings only while the
     * given item (or, with `$isWithin`, anything inside it) is selected. In
     * the logic view, every step is shown at once instead.
     *
     * @return array<string, string>
     */
    public static function shownWhenSelected(string $path, bool $isWithin = false): array
    {
        $method = $isWithin ? 'showsWithin' : 'shows';

        return [
            'x-show' => '$store.cfEditor?.' . $method . '(' . Js::from($path) . ')',
            'x-cloak' => '',
        ];
    }

    /**
     * Grid columns that follow the width of the editor's settings column
     * (a container), not the window: one, then two, and `$columns` only
     * where there's room.
     *
     * @return array<string, int>
     */
    public static function columns(int $columns): array
    {
        return match (true) {
            $columns <= 1 => ['default' => 1],
            $columns === 2 => ['default' => 1, '@xs' => 2],
            default => ['default' => 1, '@xs' => 2, '@lg' => $columns],
        };
    }

    /**
     * Attributes that show part of a step's settings only in one of the
     * editor's views: `content` (the preview, with settings on demand) or
     * `logic` (every step's conditions and rules).
     *
     * @return array<string, string>
     */
    public static function shownInView(string $view): array
    {
        return [
            'x-show' => "(\$store.cfEditor?.view ?? 'content') === " . Js::from($view),
            'x-cloak' => '',
        ];
    }

    public function steps(): Builder
    {
        return $this->editorItems(Builder::make('steps'))
            ->blocks([
                ...array_map(fn (QuestionType $type): Block => $this->questionBlock($type, withLogic: true), $this->types()),
                $this->groupBlock(),
                $this->statementBlock(),
            ]);
    }

    public function welcome(): Group
    {
        return Group::make()
            ->statePath('welcome')
            ->extraAttributes(self::shownWhenSelected('welcome'))
            ->schema([
                // Shown, and its text edited, in the preview.
                $this->editedInPreview([
                    Toggle::make('enabled')->label($this->text('show_welcome')),
                    TextInput::make('title')->label($this->text('title')),
                    Textarea::make('description')->label($this->text('description')),
                    TextInput::make('button_label')->label($this->text('button_label')),
                ]),
            ]);
    }

    public function endings(): Repeater
    {
        return Repeater::make('endings')
            ->hiddenLabel()
            ->view('conversational-forms::builder.editor.items')
            // Logic lists the endings: adding or removing one re-renders the steps' rules too.
            ->partiallyRenderAfterActionsCalled(false)
            ->schema([
                Select::make('type')->label($this->text('ending_type'))->options([
                    'end_screen' => $this->text('end_screen'),
                    'redirect' => $this->text('redirect'),
                ])->default('end_screen')->selectablePlaceholder(false),
                TextInput::make('url')->label($this->text('redirect_url'))->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: $this->text('redirect_url_help'))
                    ->required(fn (Get $get): bool => $get('type') === 'redirect')
                    ->visibleJs(<<<'JS'
                        $get('type') === 'redirect'
                        JS),
                $this->editedInPreview([
                    TextInput::make('title')->label($this->text('title')),
                    Textarea::make('description')->label($this->text('description')),
                    TextInput::make('button_label')->label($this->text('end_button_label')),
                ]),
                TextInput::make('button_url')->label($this->text('end_button_url'))->url()
                    ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: $this->text('end_button_url_help'))
                    ->visibleJs(<<<'JS'
                        $get('type') !== 'redirect'
                        JS),
                TextInput::make('key')->label($this->text('key'))->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: $this->text('ending_key_help'))
                    // A new ending has a key straight away, so logic can choose it.
                    ->default(fn ($livewire): string => $this->freeEndingKey($livewire))
                    ->required()->regex(ConversationDefinition::KEY_PATTERN)->distinct(),
            ])
            ->minItems(1)
            ->defaultItems(1);
    }

    /**
     * @return array<Component>
     */
    public function settings(): array
    {
        return [
            Section::make($this->text('behaviour'))
                ->contained(false)
                ->statePath('settings')
                ->schema([
                    Toggle::make('progress_bar')->label($this->text('progress_bar'))->default(true),
                    Toggle::make('navigation_buttons')->label($this->text('navigation_buttons'))->default(true),
                    Toggle::make('auto_advance')->label($this->text('auto_advance'))->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: $this->text('auto_advance_help'))->default(true),
                    TextInput::make('auto_advance_delay')->label($this->text('auto_advance_delay'))->integer()->minValue(0)->maxValue(3000)->suffix('ms')->placeholder('400')
                        ->visibleJs(<<<'JS'
                            $get('auto_advance')
                            JS),
                ]),
            Section::make($this->text('variables'))
                ->contained(false)
                ->description($this->text('variables_description'))
                ->schema([
                    KeyValue::make('variables')->hiddenLabel()->keyLabel($this->text('variable'))->valueLabel($this->text('starting_value'))->addActionLabel($this->text('add_variable')),
                ]),
            Section::make($this->text('hidden_fields'))
                ->contained(false)
                ->description($this->text('hidden_fields_description'))
                ->schema([
                    Repeater::make('hidden_fields')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('key')->label($this->text('key'))->required()->regex(ConversationDefinition::KEY_PATTERN),
                            TextInput::make('from_query')->label($this->text('from_query'))->placeholder($this->text('same_as_key')),
                            TextInput::make('max_length')->label($this->text('max_length'))->integer()->minValue(1)->placeholder('255'),
                        ])
                        ->columns(self::columns(2))
                        ->defaultItems(0)
                        ->addActionLabel($this->text('add_hidden_field')),
                ]),
        ];
    }

    /**
     * A builder drawn by the editor: its items' settings are shown one at a
     * time, for the item selected in the outline, which also adds, copies,
     * deletes and reorders them through the builder's actions.
     */
    private function editorItems(Builder $items): Builder
    {
        return $items
            ->hiddenLabel()
            ->view('conversational-forms::builder.editor.items')
            // Other steps' logic lists the steps: adding, copying, deleting or reordering one re-renders them all.
            ->partiallyRenderAfterActionsCalled(false)
            ->cloneable()
            ->cloneAction(fn (Action $action): Action => $action->action(function (array $arguments, Builder $component): void {
                $this->cloneItem($component, (string) $arguments['item']);
            }));
    }

    /**
     * Copy an item right after itself, with keys that aren't taken yet.
     */
    private function cloneItem(Builder $component, string $uuid): void
    {
        $items = $component->getRawState() ?? [];

        if (! array_key_exists($uuid, $items)) {
            return;
        }

        $copy = DefinitionState::copyItem($items[$uuid], DefinitionState::stepKeys($this->stateAt($component->getLivewire(), 'steps')));
        $newUuid = $component->generateUuid() ?? (string) Str::uuid();

        $copied = [];

        foreach ($items as $key => $item) {
            $copied[$key] = $item;

            if ((string) $key === $uuid) {
                $copied[$newUuid] = $copy;
            }
        }

        $component->rawState($copied);
        $component->callAfterStateUpdated();
    }

    private function questionBlock(QuestionType $type, bool $withLogic): Block
    {
        $settings = $type->getSettingsSchema();

        return Block::make($type->getName())
            ->label(fn (?array $state): string => $this->itemLabel($type->getLabel(), $state['label'] ?? null))
            ->icon($type->getIcon())
            ->schema([
                $this->inView('content', [
                    $this->editedInPreview([
                        $this->titleInput('label', $this->text('question'))->required(),
                        Textarea::make('description')->label($this->text('description')),
                    ]),
                    Toggle::make('required')->label($this->text('required')),
                    Group::make($settings)
                        ->statePath('settings')
                        ->visible($settings !== []),
                    Section::make($this->text('media'))
                        ->statePath('media')
                        ->schema([
                            FileUpload::make('url')
                                ->hiddenLabel()
                                ->image()
                                ->disk(config('conversational-forms.images.disk', 'public'))
                                ->directory(config('conversational-forms.images.directory'))
                                ->visibility('public')
                                // The preview needs the uploaded image's address from the server.
                                ->live(),
                            TextInput::make('alt')->label($this->text('image_alt'))->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: $this->text('image_alt_help')),
                        ])
                        ->contained(false)
                        ->extraAttributes(['data-cf-editor-image' => true]),
                    Section::make($this->text('quiz'))
                        ->description($this->text('quiz_description'))
                        ->statePath('correct')
                        ->schema([
                            Toggle::make('enabled')->label($this->text('quiz_enabled')),
                            Grid::make(self::columns(2))->schema([
                                TextInput::make('answer')->label($this->text('correct_answer'))->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: $this->text('correct_answer_help'))
                                    ->required(fn (Get $get): bool => (bool) $get('enabled'))
                                    ->columnSpanFull(),
                                TextInput::make('points')->label($this->text('points'))->numeric()->placeholder('1'),
                                TextInput::make('variable')->label($this->text('score_variable'))->placeholder('score')->regex('/^[A-Za-z]\w*$/'),
                            ])->visibleJs(<<<'JS'
                                $get('enabled')
                                JS),
                        ])
                        ->contained(false)
                        ->visible(in_array($type->getName(), self::QUIZ_TYPES, strict: true)),
                    $this->keyInput(),
                ]),
                $this->inView('logic', [
                    $this->visibilitySection(),
                    ...($withLogic ? [$this->logicSection()] : []),
                ]),
            ]);
    }

    /**
     * Text edited in place in the editor's preview. The fields stay (hidden),
     * so the text is validated and saved like any other setting, and a new
     * step's key still follows its text.
     *
     * @param  array<Component>  $components
     */
    private function editedInPreview(array $components): Group
    {
        return Group::make($components)->extraAttributes(['class' => 'cf-editor-edited-in-preview']);
    }

    /**
     * Part of a step's settings, shown in one of the editor's views.
     *
     * @param  array<Component>  $components
     */
    private function inView(string $view, array $components): Group
    {
        return Group::make($components)->extraAttributes(self::shownInView($view));
    }

    /**
     * The text of a step. While its key is new, the key follows it.
     */
    private function titleInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->afterStateUpdatedJs(<<<'JS'
                window.conversationalFormsEditor?.followKey($el, $state, $old, $get, $set)
                JS)
            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                if (blank($get('key'))) {
                    $set('key', DefinitionState::keyFromLabel($state));
                }
            });
    }

    private function keyInput(): TextInput
    {
        return TextInput::make('key')
            ->label($this->text('key'))
            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: $this->text('key_help'))
            ->required()
            ->regex(ConversationDefinition::KEY_PATTERN);
    }

    private function groupBlock(): Block
    {
        return Block::make('group')
            ->label(fn (?array $state): string => $this->itemLabel(__('conversational-forms::conversational-forms.builder.types.group'), $state['label'] ?? null))
            ->icon(Heroicon::OutlinedRectangleGroup)
            ->schema([
                // The group's own settings; its questions are selected one by one.
                Group::make([
                    $this->inView('content', [
                        $this->editedInPreview([
                            $this->titleInput('label', $this->text('group_title')),
                            Textarea::make('description')->label($this->text('description')),
                        ]),
                        $this->keyInput(),
                    ]),
                    $this->inView('logic', [$this->visibilitySection(), $this->logicSection()]),
                ])->extraAttributes(fn (Group $component): array => self::shownWhenSelected(self::editorPath($component->getStatePath(), $this->statePath))),
                $this->editorItems(Builder::make('questions'))
                    ->blocks(array_map(fn (QuestionType $type): Block => $this->questionBlock($type, withLogic: false), $this->types()))
                    ->minItems(1),
            ]);
    }

    private function statementBlock(): Block
    {
        return Block::make('statement')
            ->label(fn (?array $state): string => $this->itemLabel(__('conversational-forms::conversational-forms.builder.types.statement'), $state['title'] ?? null))
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->schema([
                $this->inView('content', [
                    $this->editedInPreview([
                        $this->titleInput('title', $this->text('title'))->required(),
                        Textarea::make('description')->label($this->text('description')),
                        TextInput::make('button_label')->label($this->text('button_label')),
                    ]),
                    $this->keyInput(),
                ]),
                $this->inView('logic', [$this->visibilitySection(), $this->logicSection()]),
            ]);
    }

    private function visibilitySection(): Section
    {
        return Section::make($this->text('visibility'))
            ->statePath('visibility')
            ->contained(false)
            ->schema([
                Textarea::make('raw')
                    ->label($this->text('written_in_code'))
                    ->helperText($this->text('written_in_code_help'))
                    ->readOnly()
                    ->rows(6)
                    ->visible(fn (Get $get): bool => filled($get('raw'))),
                Group::make([
                    Select::make('match')->hiddenLabel()->options([
                        'all' => $this->text('match_all'),
                        'any' => $this->text('match_any'),
                    ])->default('all')->selectablePlaceholder(false)
                        // Only a choice once there's more than one condition.
                        ->visibleJs(<<<'JS'
                            Object.keys($get('conditions') ?? {}).length > 1
                            JS),
                    Text::make($this->text('always_asked'))->color('gray')
                        ->visibleJs(<<<'JS'
                            Object.keys($get('conditions') ?? {}).length === 0
                            JS),
                    $this->conditionsRepeater(defaultItems: 0, isForRule: false)->hiddenLabel(),
                ])->visible(fn (Get $get): bool => blank($get('raw'))),
            ])
            ->contained(false);
    }

    private function logicSection(): Section
    {
        return Section::make($this->text('after_this_step'))
            ->contained(false)
            ->schema([
                Repeater::make('logic')
                    ->hiddenLabel()
                    ->schema([
                        Textarea::make('raw')
                            ->label($this->text('written_in_code'))
                            ->helperText($this->text('written_in_code_help'))
                            ->readOnly()
                            ->rows(6)
                            ->visible(fn (Get $get): bool => filled($get('raw'))),
                        Group::make([
                            Select::make('match')->hiddenLabel()->options([
                                'always' => $this->text('match_always'),
                                'all' => $this->text('match_all'),
                                'any' => $this->text('match_any'),
                            ])->default('all')->selectablePlaceholder(false)->live(),
                            $this->conditionsRepeater(defaultItems: 1, isForRule: true)->hiddenLabel()->visible(fn (Get $get): bool => $get('match') !== 'always'),
                            Grid::make(self::columns(2))->schema([
                                Select::make('action')->hiddenLabel()->prefix($this->text('then'))->options([
                                    'jump_to' => $this->text('action_jump_to'),
                                    'end' => $this->text('action_end'),
                                    'end_with' => $this->text('action_end_with'),
                                    'none' => $this->text('action_none'),
                                ])->default('jump_to')->selectablePlaceholder(false)->live(),
                                // Changes go to the server at once, so the rule's summary above stays current.
                                Select::make('target')->hiddenLabel()->placeholder($this->text('target_step'))->live()
                                    ->options(fn (Get $get, $livewire): array => $this->laterSteps($livewire, (string) $get('../../key')))
                                    ->required(fn (Get $get): bool => $get('action') === 'jump_to')
                                    ->visible(fn (Get $get): bool => $get('action') === 'jump_to'),
                                Select::make('ending')->hiddenLabel()->placeholder($this->text('target_ending'))->live()
                                    ->options(fn ($livewire): array => $this->endingOptions($livewire))
                                    ->required(fn (Get $get): bool => $get('action') === 'end_with')
                                    ->visible(fn (Get $get): bool => $get('action') === 'end_with'),
                            ]),
                            Repeater::make('calculations')
                                ->label($this->text('calculations'))
                                ->schema([
                                    Select::make('operation')->hiddenLabel()->options(collect(CalculationOperator::cases())->mapWithKeys(fn (CalculationOperator $operator): array => [
                                        $operator->value => $this->text("calculation_{$operator->value}"),
                                    ])->all())->default('add')->selectablePlaceholder(false)->live(),
                                    TextInput::make('variable')->hiddenLabel()->placeholder($this->text('variable'))->required()->live(onBlur: true)
                                        ->datalist(fn ($livewire): array => array_keys($this->stateAt($livewire, 'variables'))),
                                    TextInput::make('value')->hiddenLabel()->placeholder($this->text('value'))->live(onBlur: true),
                                ])
                                ->partiallyRenderAfterActionsCalled(false)
                                ->columns(self::columns(3))
                                ->defaultItems(0)
                                ->addActionLabel($this->text('add_calculation'))
                                ->addAction(fn (Action $action): Action => $action->link()->icon(Heroicon::Plus))
                                ->addActionAlignment(Alignment::Start),
                        ])->visible(fn (Get $get): bool => blank($get('raw'))),
                    ])
                    // Each rule reads as a sentence; it opens to be changed.
                    ->itemLabel(fn (array $state, $livewire): string => RuleSummary::describe($state, $this->stepLabels($livewire), $this->questionLabels($livewire)))
                    ->collapsible()
                    ->collapsed()
                    ->reorderable()
                    ->defaultItems(0)
                    ->addActionLabel($this->text('add_rule'))
                    ->addAction(fn (Action $action): Action => $action->link()->icon(Heroicon::Plus))
                    ->addActionAlignment(Alignment::Start),
                Text::make($this->text('otherwise_next'))->color('gray'),
            ]);
    }

    /**
     * @param  int  $defaultItems  New rules start with a condition; visibility starts without.
     * @param  bool  $isForRule  A rule (after its step is answered) rather than "show only when" (before it's asked).
     */
    private function conditionsRepeater(int $defaultItems, bool $isForRule): Repeater
    {
        // The key of the step (or question in a group) these conditions belong to: from a
        // condition, up through `conditions` and the rule row to `logic`, or to `visibility`.
        $stepKey = fn (Get $get): string => (string) $get($isForRule ? '../../../../key' : '../../../key');

        $choiceOptions = fn (Get $get, $livewire): array => $this->choiceOptions($livewire, (string) $get('field'));
        $valueInput = fn (Get $get, $livewire): string => $this->valueInput($livewire, (string) $get('field'), Operator::tryFrom((string) $get('operator')));

        return Repeater::make('conditions')
            ->label($this->text('conditions'))
            ->schema([
                Select::make('field')->hiddenLabel()->placeholder($this->text('field'))
                    ->options(fn (Get $get, $livewire): array => $this->fieldOptions($livewire, $stepKey($get), includesStep: $isForRule))
                    // A new rule usually checks the answer just given.
                    ->default(fn (Get $get, $livewire): ?string => $isForRule ? $this->questionKeyOf($livewire, $stepKey($get)) : null)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('operator', null)),
                Select::make('operator')->hiddenLabel()->placeholder($this->text('operator'))
                    ->options(fn (Get $get, $livewire): array => collect(Operator::forAnswerType($this->answerTypeOf($livewire, (string) $get('field'))))
                        ->mapWithKeys(fn (Operator $operator): array => [$operator->value => $operator->getLabel()])
                        ->all())
                    ->required()
                    ->live(),
                Select::make('value')->hiddenLabel()->placeholder($this->text('value'))->live()
                    ->options(fn (Get $get, $livewire): array => ($this->answerTypeOf($livewire, (string) $get('field')) === AnswerType::Boolean)
                        ? ['1' => $this->text('yes'), '0' => $this->text('no')]
                        : $choiceOptions($get, $livewire))
                    ->visible(fn (Get $get, $livewire): bool => $valueInput($get, $livewire) === 'select'),
                TextInput::make('value')->hiddenLabel()->placeholder($this->text('value'))->live(onBlur: true)
                    ->type(fn (Get $get, $livewire): string => match ($this->answerTypeOf($livewire, (string) $get('field'))) {
                        AnswerType::Date => 'date',
                        AnswerType::Number => 'number',
                        default => 'text',
                    })
                    ->visible(fn (Get $get, $livewire): bool => $valueInput($get, $livewire) === 'text'),
                TagsInput::make('values')->hiddenLabel()->live()
                    ->placeholder(fn (Get $get): string => ($get('operator') === Operator::Between->value) ? $this->text('between_placeholder') : $this->text('values_placeholder'))
                    ->suggestions(fn (Get $get, $livewire): array => array_keys($choiceOptions($get, $livewire)))
                    ->visible(fn (Get $get, $livewire): bool => $valueInput($get, $livewire) === 'list'),
            ])
            ->columns(self::columns(3))
            ->reorderable(false)
            // Adding or removing a condition re-renders the whole rule, so its summary stays current.
            ->partiallyRenderAfterActionsCalled(false)
            // One line per condition, its delete button at the end (see builder.css).
            ->extraAttributes(['class' => 'cf-editor-conditions'])
            ->defaultItems($defaultItems)
            ->addActionLabel($this->text('add_condition'))
            ->addAction(fn (Action $action): Action => $action->link()->icon(Heroicon::Plus))
            ->addActionAlignment(Alignment::Start);
    }

    /**
     * How a condition's value is entered: `select` (yes / no, or a choice),
     * `text`, `list` (several values), or `none` (`is empty`).
     */
    private function valueInput(mixed $livewire, string $field, ?Operator $operator): string
    {
        if ((! $operator instanceof Operator) || (! $operator->needsValue())) {
            return 'none';
        }

        if ($operator->takesList()) {
            return 'list';
        }

        $type = $this->answerTypeOf($livewire, $field);

        return in_array($type, [AnswerType::Boolean, AnswerType::Choice, AnswerType::Choices], strict: true) ? 'select' : 'text';
    }

    /**
     * @return array<string, array<string, string>>
     */
    /**
     * What conditions can check: the questions answered by the time they're
     * checked, then hidden fields and variables. A rule runs after its step,
     * so it can check that step's answers and earlier ones; "show only when"
     * runs before its step, so only earlier ones (and, in a group, the
     * group's earlier questions).
     *
     * @return array<string, array<string, string>>
     */
    private function fieldOptions(mixed $livewire, string $stepKey, bool $includesStep): array
    {
        $current = [];
        $earlier = [];
        $position = $this->positions($livewire)[$stepKey] ?? null;

        foreach ($this->questionPositions($livewire) as $key => [$step, $inGroup, $label]) {
            $isEarlier = ($position === null)
                || ($step < $position[0])
                // A question in a group can also check the group's earlier questions, on the same screen.
                || (($step === $position[0]) && ($position[1] !== null) && ($inGroup !== null) && ($inGroup < $position[1]));

            match (true) {
                $isEarlier => $earlier[$key] = $label,
                // A rule runs after its step, so it can check that step's own answers.
                $includesStep && ($step === $position[0]) => $current[$key] = $label,
                default => null,
            };
        }

        $hiddenFields = [];

        foreach ($this->stateAt($livewire, 'hidden_fields') as $field) {
            if (filled($field['key'] ?? null)) {
                $hiddenFields[$field['key']] = (string) $field['key'];
            }
        }

        $variables = [];

        foreach (array_keys($this->stateAt($livewire, 'variables')) as $variable) {
            $variables["@{$variable}"] = "@{$variable}";
        }

        // A group's step is a screen of several questions.
        $isScreen = ($position !== null) && ($position[1] === null) && (! array_key_exists($stepKey, $current));

        return array_filter([
            $this->text($isScreen ? 'this_screen' : 'this_question') => $current,
            $this->text('earlier_answers') => $earlier,
            $this->text('hidden_fields') => $hiddenFields,
            $this->text('variables') => $variables,
        ]);
    }

    /**
     * Where each step and each question in a group sits: `[step index, index
     * in its group or null]`, keyed by key.
     *
     * @return array<string, array{int, int|null}>
     */
    private function positions(mixed $livewire): array
    {
        $positions = [];

        foreach (array_values($this->stateAt($livewire, 'steps')) as $index => $item) {
            if (filled($item['data']['key'] ?? null)) {
                $positions[(string) $item['data']['key']] = [$index, null];
            }

            foreach (array_values($item['data']['questions'] ?? []) as $inGroup => $question) {
                if (filled($question['data']['key'] ?? null)) {
                    $positions[(string) $question['data']['key']] = [$index, $inGroup];
                }
            }
        }

        return $positions;
    }

    /**
     * Every question, in order, with its position and its label.
     *
     * @return array<string, array{int, int|null, string}>
     */
    private function questionPositions(mixed $livewire): array
    {
        $positions = $this->positions($livewire);
        $questions = [];

        foreach (DefinitionState::questionItems($this->stateAt($livewire, 'steps')) as $item) {
            $key = (string) ($item['data']['key'] ?? '');

            if (($key !== '') && isset($positions[$key])) {
                $questions[$key] = [...$positions[$key], $this->readable($key, $item['data']['label'] ?? null)];
            }
        }

        return $questions;
    }

    /**
     * The step's key when the step is a question (not a statement or group).
     */
    private function questionKeyOf(mixed $livewire, string $stepKey): ?string
    {
        return array_key_exists($stepKey, $this->questionPositions($livewire)) ? $stepKey : null;
    }

    private function answerTypeOf(mixed $livewire, string $field): AnswerType
    {
        if (str_starts_with($field, '@')) {
            return AnswerType::Number;
        }

        $field = strtok($field, '.') ?: $field;

        return DefinitionState::answerTypes($this->stateAt($livewire, 'steps'))[$field] ?? AnswerType::Text;
    }

    /**
     * The options of a choice question, as value => label.
     *
     * @return array<string, string>
     */
    private function choiceOptions(mixed $livewire, string $field): array
    {
        foreach (DefinitionState::questionItems($this->stateAt($livewire, 'steps')) as $item) {
            if (($item['data']['key'] ?? null) !== $field) {
                continue;
            }

            $options = [];

            foreach ($item['data']['settings']['options'] ?? [] as $option) {
                $label = trim((string) ($option['label'] ?? ''));

                if ($label !== '') {
                    $options[filled($option['value'] ?? null) ? (string) $option['value'] : Str::slug($label, '_')] = $label;
                }
            }

            return $options;
        }

        return [];
    }

    /**
     * Steps after the given one: logic only jumps forward.
     *
     * @return array<string, string>
     */
    private function laterSteps(mixed $livewire, string $current): array
    {
        $steps = [];
        $isAfter = false;

        foreach ($this->stateAt($livewire, 'steps') as $item) {
            $key = $item['data']['key'] ?? null;

            if (blank($key)) {
                continue;
            }

            if ($isAfter) {
                $steps[$key] = $this->readable($key, $item['data']['label'] ?? $item['data']['title'] ?? null);
            }

            $isAfter = $isAfter || ($key === $current);
        }

        return $steps;
    }

    /**
     * @return array<string, string>
     */
    private function stepLabels(mixed $livewire): array
    {
        $labels = [];

        foreach ($this->stateAt($livewire, 'steps') as $item) {
            if (filled($item['data']['key'] ?? null)) {
                $labels[$item['data']['key']] = $this->quoted($item['data']['label'] ?? $item['data']['title'] ?? null, (string) $item['data']['key']);
            }
        }

        return $labels;
    }

    /**
     * Questions by key, as people read them in rules.
     *
     * @return array<string, string>
     */
    private function questionLabels(mixed $livewire): array
    {
        $labels = [];

        foreach (DefinitionState::questionItems($this->stateAt($livewire, 'steps')) as $item) {
            if (filled($item['data']['key'] ?? null)) {
                $labels[$item['data']['key']] = $this->quoted($item['data']['label'] ?? null, (string) $item['data']['key']);
            }
        }

        return $labels;
    }

    private function quoted(?string $text, string $fallback): string
    {
        $text = trim(strip_tags((string) $text));

        return ($text === '') ? $fallback : '“' . str($text)->limit(40) . '”';
    }

    /**
     * @return array<string, string>
     */
    private function endingOptions(mixed $livewire): array
    {
        $endings = [];

        foreach ($this->stateAt($livewire, 'endings') as $ending) {
            if (filled($ending['key'] ?? null)) {
                $endings[$ending['key']] = $this->readable($ending['key'], $ending['title'] ?? null);
            }
        }

        return $endings;
    }

    /**
     * `ending_2`, `ending_3`…: the first that no ending uses yet.
     */
    private function freeEndingKey(mixed $livewire): string
    {
        // The new ending is already in the list, still without a key.
        $keys = array_values(array_filter(array_column($this->stateAt($livewire, 'endings'), 'key'), filled(...)));
        $number = count($keys) + 1;

        while (in_array("ending_{$number}", $keys, strict: true)) {
            $number++;
        }

        return "ending_{$number}";
    }

    /**
     * @return array<array-key, mixed>
     */
    private function stateAt(mixed $livewire, string $key): array
    {
        $state = data_get($livewire, "{$this->statePath}.{$key}");

        return is_array($state) ? $state : [];
    }

    /**
     * @return list<QuestionType>
     */
    private function types(): array
    {
        return array_values(app(QuestionTypeRegistry::class)->all());
    }

    /**
     * A step or ending as people know it in pickers: its text, or its key until it has one.
     */
    private function readable(string $key, ?string $text): string
    {
        $text = trim(strip_tags((string) $text));

        return ($text === '') ? $key : (string) str($text)->limit(60);
    }

    private function itemLabel(string $prefix, ?string $text): string
    {
        return filled($text) ? "{$prefix} · " . str(strip_tags($text))->limit(60) : $prefix;
    }

    private function text(string $key): string
    {
        return __("conversational-forms::conversational-forms.builder.form.{$key}");
    }
}
