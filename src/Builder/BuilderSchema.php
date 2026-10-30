<?php

namespace JaOcero\ConversationalForms\Builder;

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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
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

    public function steps(): Builder
    {
        return Builder::make('steps')
            ->hiddenLabel()
            ->blocks([
                ...array_map(fn (QuestionType $type): Block => $this->questionBlock($type, withLogic: true), $this->types()),
                $this->groupBlock(),
                $this->statementBlock(),
            ])
            ->blockPickerColumns(3)
            ->blockPickerWidth('3xl')
            ->collapsible()
            ->cloneable()
            ->addActionLabel($this->text('add_step'))
            ->addBetweenActionLabel($this->text('insert_step'));
    }

    /**
     * @return array<Component>
     */
    public function welcomeAndEndings(): array
    {
        return [
            Section::make($this->text('welcome'))
                ->description($this->text('welcome_description'))
                ->statePath('welcome')
                ->schema([
                    Toggle::make('enabled')->label($this->text('show_welcome'))->live(),
                    Grid::make(2)->schema([
                        TextInput::make('title')->label($this->text('title')),
                        TextInput::make('button_label')->label($this->text('button_label'))->placeholder(__('conversational-forms::conversational-forms.welcome_screen.start')),
                        Textarea::make('description')->label($this->text('description'))->rows(2)->columnSpanFull(),
                    ])->visible(fn (Get $get): bool => (bool) $get('enabled')),
                ]),
            Section::make($this->text('endings'))
                ->description($this->text('endings_description'))
                ->schema([
                    Repeater::make('endings')
                        ->hiddenLabel()
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('key')->label($this->text('key'))->required()->regex(ConversationDefinition::KEY_PATTERN)->distinct(),
                                Select::make('type')->label($this->text('ending_type'))->options([
                                    'end_screen' => $this->text('end_screen'),
                                    'redirect' => $this->text('redirect'),
                                ])->default('end_screen')->selectablePlaceholder(false)->live(),
                                TextInput::make('url')->label($this->text('redirect_url'))->helperText($this->text('redirect_url_help'))
                                    ->required(fn (Get $get): bool => $get('type') === 'redirect')
                                    ->visible(fn (Get $get): bool => $get('type') === 'redirect'),
                            ]),
                            Grid::make(2)->schema([
                                TextInput::make('title')->label($this->text('title'))->helperText($this->text('piping_help')),
                                Textarea::make('description')->label($this->text('description'))->rows(1),
                                TextInput::make('button_label')->label($this->text('end_button_label')),
                                TextInput::make('button_url')->label($this->text('end_button_url'))->url(),
                            ])->visible(fn (Get $get): bool => $get('type') !== 'redirect'),
                        ])
                        ->itemLabel(fn (array $state): string => ($state['key'] ?? '') . (filled($state['title'] ?? null) ? " · {$state['title']}" : ''))
                        ->collapsible()
                        ->reorderable()
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel($this->text('add_ending')),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    public function settings(): array
    {
        return [
            Section::make($this->text('behaviour'))
                ->statePath('settings')
                ->columns(2)
                ->schema([
                    Toggle::make('progress_bar')->label($this->text('progress_bar'))->default(true),
                    Toggle::make('navigation_buttons')->label($this->text('navigation_buttons'))->default(true),
                    Toggle::make('auto_advance')->label($this->text('auto_advance'))->helperText($this->text('auto_advance_help'))->default(true)->live(),
                    TextInput::make('auto_advance_delay')->label($this->text('auto_advance_delay'))->integer()->minValue(0)->maxValue(3000)->suffix('ms')->placeholder('400')
                        ->visible(fn (Get $get): bool => (bool) $get('auto_advance')),
                ]),
            Section::make($this->text('variables'))
                ->description($this->text('variables_description'))
                ->schema([
                    KeyValue::make('variables')->hiddenLabel()->keyLabel($this->text('variable'))->valueLabel($this->text('starting_value'))->addActionLabel($this->text('add_variable')),
                ]),
            Section::make($this->text('hidden_fields'))
                ->description($this->text('hidden_fields_description'))
                ->schema([
                    Repeater::make('hidden_fields')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('key')->label($this->text('key'))->required()->regex(ConversationDefinition::KEY_PATTERN),
                            TextInput::make('from_query')->label($this->text('from_query'))->placeholder($this->text('same_as_key')),
                            TextInput::make('max_length')->label($this->text('max_length'))->integer()->minValue(1)->placeholder('255'),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->addActionLabel($this->text('add_hidden_field')),
                ]),
        ];
    }

    private function questionBlock(QuestionType $type, bool $withLogic): Block
    {
        $settings = $type->getSettingsSchema();

        return Block::make($type->getName())
            ->label(fn (?array $state): string => $this->itemLabel($type->getLabel(), $state['label'] ?? null))
            ->icon($type->getIcon())
            ->schema([
                ...$this->questionBasics(),
                Section::make($this->text('settings'))
                    ->statePath('settings')
                    ->schema($settings)
                    ->compact()
                    ->collapsible()
                    ->visible($settings !== []),
                Section::make($this->text('quiz'))
                    ->description($this->text('quiz_description'))
                    ->statePath('correct')
                    ->schema([
                        Toggle::make('enabled')->label($this->text('quiz_enabled'))->live(),
                        Grid::make(3)->schema([
                            TextInput::make('answer')->label($this->text('correct_answer'))->helperText($this->text('correct_answer_help'))->required(),
                            TextInput::make('points')->label($this->text('points'))->numeric()->placeholder('1'),
                            TextInput::make('variable')->label($this->text('score_variable'))->placeholder('score')->regex('/^[A-Za-z]\w*$/'),
                        ])->visible(fn (Get $get): bool => (bool) $get('enabled')),
                    ])
                    ->compact()
                    ->collapsible()
                    ->collapsed(fn (Get $get): bool => ! $get('correct.enabled'))
                    ->visible(in_array($type->getName(), self::QUIZ_TYPES, strict: true)),
                Section::make($this->text('media'))
                    ->statePath('media')
                    ->schema([
                        Grid::make(2)->schema([
                            FileUpload::make('url')
                                ->label($this->text('image'))
                                ->image()
                                ->disk(config('conversational-forms.images.disk', 'public'))
                                ->directory(config('conversational-forms.images.directory'))
                                ->visibility('public'),
                            TextInput::make('alt')->label($this->text('image_alt'))->helperText($this->text('image_alt_help')),
                        ]),
                    ])
                    ->compact()
                    ->collapsible()
                    ->collapsed(fn (Get $get): bool => blank($get('media.url'))),
                $this->visibilitySection(),
                ...($withLogic ? [$this->logicSection()] : []),
            ]);
    }

    /**
     * @return array<Component>
     */
    private function questionBasics(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 3])->schema([
                TextInput::make('label')
                    ->label($this->text('question'))
                    ->helperText($this->text('piping_help'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if (blank($get('key'))) {
                            $set('key', DefinitionState::keyFromLabel($state));
                        }
                    })
                    ->columnSpan(['md' => 2]),
                TextInput::make('key')
                    ->label($this->text('key'))
                    ->helperText($this->text('key_help'))
                    ->required()
                    ->regex(ConversationDefinition::KEY_PATTERN)
                    ->live(onBlur: true),
                Textarea::make('description')->label($this->text('description'))->rows(1)->columnSpan(['md' => 2]),
                Toggle::make('required')->label($this->text('required'))->inline(false),
            ]),
        ];
    }

    private function groupBlock(): Block
    {
        return Block::make('group')
            ->label(fn (?array $state): string => $this->itemLabel(__('conversational-forms::conversational-forms.builder.types.group'), $state['label'] ?? null))
            ->icon(Heroicon::OutlinedRectangleGroup)
            ->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('label')->label($this->text('group_title'))->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if (blank($get('key'))) {
                                $set('key', DefinitionState::keyFromLabel($state));
                            }
                        })
                        ->columnSpan(['md' => 2]),
                    TextInput::make('key')->label($this->text('key'))->required()->regex(ConversationDefinition::KEY_PATTERN),
                    Textarea::make('description')->label($this->text('description'))->rows(1)->columnSpanFull(),
                ]),
                Builder::make('questions')
                    ->label($this->text('group_questions'))
                    ->blocks(array_map(fn (QuestionType $type): Block => $this->questionBlock($type, withLogic: false), $this->types()))
                    ->blockPickerColumns(3)
                    ->collapsible()
                    ->minItems(1)
                    ->addActionLabel($this->text('add_question')),
                $this->visibilitySection(),
                $this->logicSection(),
            ]);
    }

    private function statementBlock(): Block
    {
        return Block::make('statement')
            ->label(fn (?array $state): string => $this->itemLabel(__('conversational-forms::conversational-forms.builder.types.statement'), $state['title'] ?? null))
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('title')->label($this->text('title'))->required()->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if (blank($get('key'))) {
                                $set('key', DefinitionState::keyFromLabel($state));
                            }
                        })
                        ->columnSpan(['md' => 2]),
                    TextInput::make('key')->label($this->text('key'))->required()->regex(ConversationDefinition::KEY_PATTERN),
                    Textarea::make('description')->label($this->text('description'))->rows(2)->columnSpan(['md' => 2]),
                    TextInput::make('button_label')->label($this->text('button_label'))->placeholder(__('conversational-forms::conversational-forms.navigation.continue')),
                ]),
                $this->visibilitySection(),
                $this->logicSection(),
            ]);
    }

    private function visibilitySection(): Section
    {
        return Section::make($this->text('visibility'))
            ->description($this->text('visibility_description'))
            ->statePath('visibility')
            ->schema([
                Textarea::make('raw')
                    ->label($this->text('written_in_code'))
                    ->helperText($this->text('written_in_code_help'))
                    ->readOnly()
                    ->rows(6)
                    ->visible(fn (Get $get): bool => filled($get('raw'))),
                Group::make([
                    Select::make('match')->label($this->text('show_when'))->options([
                        'all' => $this->text('match_all'),
                        'any' => $this->text('match_any'),
                    ])->default('all')->selectablePlaceholder(false),
                    $this->conditionsRepeater(defaultItems: 0),
                ])->visible(fn (Get $get): bool => blank($get('raw'))),
            ])
            ->compact()
            ->collapsible()
            ->collapsed(fn (Get $get): bool => blank($get('visibility.conditions')) && blank($get('visibility.raw')));
    }

    private function logicSection(): Section
    {
        return Section::make($this->text('logic'))
            ->description($this->text('logic_description'))
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
                            Select::make('match')->label($this->text('when'))->options([
                                'always' => $this->text('match_always'),
                                'all' => $this->text('match_all'),
                                'any' => $this->text('match_any'),
                            ])->default('all')->selectablePlaceholder(false)->live(),
                            $this->conditionsRepeater(defaultItems: 1)->visible(fn (Get $get): bool => $get('match') !== 'always'),
                            Grid::make(2)->schema([
                                Select::make('action')->label($this->text('then'))->options([
                                    'jump_to' => $this->text('action_jump_to'),
                                    'end' => $this->text('action_end'),
                                    'end_with' => $this->text('action_end_with'),
                                    'none' => $this->text('action_none'),
                                ])->default('jump_to')->selectablePlaceholder(false)->live(),
                                Select::make('target')->label($this->text('target_step'))
                                    ->options(fn (Get $get, $livewire): array => $this->laterSteps($livewire, (string) $get('../../key')))
                                    ->required(fn (Get $get): bool => $get('action') === 'jump_to')
                                    ->visible(fn (Get $get): bool => $get('action') === 'jump_to'),
                                Select::make('ending')->label($this->text('target_ending'))
                                    ->options(fn ($livewire): array => $this->endings($livewire))
                                    ->required(fn (Get $get): bool => $get('action') === 'end_with')
                                    ->visible(fn (Get $get): bool => $get('action') === 'end_with'),
                            ]),
                            Repeater::make('calculations')
                                ->label($this->text('calculations'))
                                ->schema([
                                    Select::make('operation')->hiddenLabel()->options(collect(CalculationOperator::cases())->mapWithKeys(fn (CalculationOperator $operator): array => [
                                        $operator->value => $this->text("calculation_{$operator->value}"),
                                    ])->all())->default('add')->selectablePlaceholder(false),
                                    TextInput::make('variable')->hiddenLabel()->placeholder($this->text('variable'))->required()
                                        ->datalist(fn ($livewire): array => array_keys($this->stateAt($livewire, 'variables'))),
                                    TextInput::make('value')->hiddenLabel()->placeholder($this->text('value')),
                                ])
                                ->columns(3)
                                ->defaultItems(0)
                                ->addActionLabel($this->text('add_calculation')),
                        ])->visible(fn (Get $get): bool => blank($get('raw'))),
                    ])
                    ->itemLabel(fn (array $state, $livewire): string => RuleSummary::describe($state, $this->stepLabels($livewire)))
                    ->collapsible()
                    ->reorderable()
                    ->defaultItems(0)
                    ->addActionLabel($this->text('add_rule')),
            ])
            ->compact()
            ->collapsible()
            ->collapsed(fn (Get $get): bool => blank($get('logic')));
    }

    /**
     * @param  int  $defaultItems  New rules start with a condition; visibility starts without.
     */
    private function conditionsRepeater(int $defaultItems): Repeater
    {
        $choiceOptions = fn (Get $get, $livewire): array => $this->choiceOptions($livewire, (string) $get('field'));
        $valueInput = fn (Get $get, $livewire): string => $this->valueInput($livewire, (string) $get('field'), Operator::tryFrom((string) $get('operator')));

        return Repeater::make('conditions')
            ->label($this->text('conditions'))
            ->schema([
                Select::make('field')->hiddenLabel()->placeholder($this->text('field'))
                    ->options(fn ($livewire): array => $this->fieldOptions($livewire))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('operator', null)),
                Select::make('operator')->hiddenLabel()->placeholder($this->text('operator'))
                    ->options(fn (Get $get, $livewire): array => collect(Operator::forAnswerType($this->answerTypeOf($livewire, (string) $get('field'))))
                        ->mapWithKeys(fn (Operator $operator): array => [$operator->value => $operator->getLabel()])
                        ->all())
                    ->required()
                    ->live(),
                Select::make('value')->hiddenLabel()->placeholder($this->text('value'))
                    ->options(fn (Get $get, $livewire): array => ($this->answerTypeOf($livewire, (string) $get('field')) === AnswerType::Boolean)
                        ? ['1' => $this->text('yes'), '0' => $this->text('no')]
                        : $choiceOptions($get, $livewire))
                    ->visible(fn (Get $get, $livewire): bool => $valueInput($get, $livewire) === 'select'),
                TextInput::make('value')->hiddenLabel()->placeholder($this->text('value'))
                    ->type(fn (Get $get, $livewire): string => match ($this->answerTypeOf($livewire, (string) $get('field'))) {
                        AnswerType::Date => 'date',
                        AnswerType::Number => 'number',
                        default => 'text',
                    })
                    ->visible(fn (Get $get, $livewire): bool => $valueInput($get, $livewire) === 'text'),
                TagsInput::make('values')->hiddenLabel()
                    ->placeholder(fn (Get $get): string => ($get('operator') === Operator::Between->value) ? $this->text('between_placeholder') : $this->text('values_placeholder'))
                    ->suggestions(fn (Get $get, $livewire): array => array_keys($choiceOptions($get, $livewire)))
                    ->visible(fn (Get $get, $livewire): bool => $valueInput($get, $livewire) === 'list'),
            ])
            ->columns(3)
            ->defaultItems($defaultItems)
            ->addActionLabel($this->text('add_condition'));
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
    private function fieldOptions(mixed $livewire): array
    {
        $questions = [];

        foreach (DefinitionState::questionItems($this->stateAt($livewire, 'steps')) as $item) {
            $key = $item['data']['key'] ?? null;

            if (filled($key)) {
                $questions[$key] = $this->itemLabel($key, $item['data']['label'] ?? null);
            }
        }

        foreach ($this->stateAt($livewire, 'hidden_fields') as $field) {
            if (filled($field['key'] ?? null)) {
                $questions[$field['key']] = "{$field['key']} · " . $this->text('hidden_field');
            }
        }

        $variables = [];

        foreach (array_keys($this->stateAt($livewire, 'variables')) as $variable) {
            $variables["@{$variable}"] = "@{$variable}";
        }

        return array_filter([
            $this->text('questions') => $questions,
            $this->text('variables') => $variables,
        ]);
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
                $steps[$key] = $this->itemLabel($key, $item['data']['label'] ?? $item['data']['title'] ?? null);
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
                $labels[$item['data']['key']] = (string) $item['data']['key'];
            }
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private function endings(mixed $livewire): array
    {
        $endings = [];

        foreach ($this->stateAt($livewire, 'endings') as $ending) {
            if (filled($ending['key'] ?? null)) {
                $endings[$ending['key']] = $this->itemLabel($ending['key'], $ending['title'] ?? null);
            }
        }

        return $endings;
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

    private function itemLabel(string $prefix, ?string $text): string
    {
        return filled($text) ? "{$prefix} · " . str(strip_tags($text))->limit(60) : $prefix;
    }

    private function text(string $key): string
    {
        return __("conversational-forms::conversational-forms.builder.form.{$key}");
    }
}
