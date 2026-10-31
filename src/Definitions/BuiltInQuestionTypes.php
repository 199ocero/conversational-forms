<?php

namespace JaOcero\ConversationalForms\Definitions;

use Filament\Forms\Components\FileUpload as FileUploadField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use JaOcero\ConversationalForms\Builder\BuilderSchema;
use JaOcero\ConversationalForms\Logic\Enums\AnswerType;
use JaOcero\ConversationalForms\Questions;

/**
 * The question types that ship with the package, with their builder fields.
 * Each field's name is a key of the question's `settings` in a definition.
 */
final class BuiltInQuestionTypes
{
    /**
     * @return list<QuestionType>
     */
    public static function all(): array
    {
        $choiceAnswer = fn (array $settings): AnswerType => ($settings['multiple'] ?? false) ? AnswerType::Choices : AnswerType::Choice;

        return [
            QuestionType::make('short_text', Questions\ShortText::class)->previewView(self::preview('text-input'))
                ->icon(Heroicon::OutlinedBars2)->group('text')
                ->settingsSchema(fn (): array => self::textSettings()),
            QuestionType::make('long_text', Questions\LongText::class)->previewView(self::preview('long-text'))
                ->icon(Heroicon::OutlinedBars4)->group('text')
                ->settingsSchema(fn (): array => [
                    ...self::textSettings(),
                    Grid::make(BuilderSchema::columns(2))->schema([
                        TextInput::make('rows')->label(self::field('rows'))->integer()->minValue(1)->maxValue(20)->placeholder('3'),
                        Toggle::make('submit_on_enter')->label(self::field('submit_on_enter'))->default(true)->inline(false),
                    ]),
                ]),
            QuestionType::make('email', Questions\Email::class)->previewView(self::preview('text-input'))
                ->icon(Heroicon::OutlinedEnvelope)->group('text')
                ->settingsSchema(fn (): array => [self::placeholder()]),
            QuestionType::make('website', Questions\Website::class)->previewView(self::preview('text-input'))
                ->icon(Heroicon::OutlinedLink)->group('text')
                ->settingsSchema(fn (): array => [self::placeholder()]),
            QuestionType::make('phone', Questions\Phone::class)->previewView(self::preview('text-input'))
                ->icon(Heroicon::OutlinedPhone)->group('text')
                ->settingsSchema(fn (): array => [
                    self::placeholder(),
                    Toggle::make('normalize')->label(self::field('normalize'))->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: self::help('normalize'))->default(true),
                ]),
            QuestionType::make('number', Questions\Number::class)->previewView(self::preview('text-input'))
                ->icon(Heroicon::OutlinedHashtag)->group('text')->answerType(AnswerType::Number)
                ->settingsSchema(fn (): array => [
                    self::placeholder(),
                    Grid::make(BuilderSchema::columns(3))->schema([
                        TextInput::make('min')->label(self::field('min'))->numeric(),
                        TextInput::make('max')->label(self::field('max'))->numeric(),
                        Toggle::make('integer')->label(self::field('integer'))->inline(false),
                    ]),
                ]),

            QuestionType::make('multiple_choice', Questions\MultipleChoice::class)->previewView(self::preview('choices'))
                ->icon(Heroicon::OutlinedListBullet)->group('choice')->answerType($choiceAnswer)
                ->settingsSchema(fn (): array => [self::options(), ...self::choiceSettings()]),
            QuestionType::make('picture_choice', Questions\PictureChoice::class)->previewView(self::preview('pictures'))
                ->icon(Heroicon::OutlinedPhoto)->group('choice')->answerType($choiceAnswer)
                ->settingsSchema(fn (): array => [
                    self::options(withImages: true),
                    ...self::choiceSettings(),
                    Toggle::make('hidden_labels')->label(self::field('hidden_labels')),
                ]),
            QuestionType::make('dropdown', Questions\Dropdown::class)->previewView(self::preview('dropdown'))
                ->icon(Heroicon::OutlinedChevronUpDown)->group('choice')->answerType(AnswerType::Choice)
                ->settingsSchema(fn (): array => [self::options()]),
            QuestionType::make('yes_no', Questions\YesNo::class)->previewView(self::preview('yes-no'))
                ->icon(Heroicon::OutlinedHandThumbUp)->group('choice')->answerType(AnswerType::Boolean)
                ->settingsSchema(fn (): array => self::yesNoLabels()),
            QuestionType::make('legal_consent', Questions\LegalConsent::class)->previewView(self::preview('yes-no'))
                ->icon(Heroicon::OutlinedScale)->group('choice')->answerType(AnswerType::Boolean)
                ->settingsSchema(fn (): array => [
                    Textarea::make('terms')->label(self::field('terms'))->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: self::help('terms'))->rows(5),
                    ...self::yesNoLabels(),
                    Toggle::make('must_accept')->label(self::field('must_accept')),
                ]),
            QuestionType::make('checkbox', Questions\Checkbox::class)->previewView(self::preview('checkbox'))
                ->icon(Heroicon::OutlinedCheckCircle)->group('choice')->answerType(AnswerType::Boolean)
                ->settingsSchema(fn (): array => [
                    TextInput::make('checkbox_label')->label(self::field('checkbox_label')),
                    Toggle::make('must_accept')->label(self::field('must_accept')),
                ]),

            QuestionType::make('rating', Questions\Rating::class)->previewView(self::preview('rating'))
                ->icon(Heroicon::OutlinedStar)->group('scale')->answerType(AnswerType::Number)
                ->settingsSchema(fn (): array => [
                    Grid::make(BuilderSchema::columns(2))->schema([
                        TextInput::make('steps')->label(self::field('steps'))->integer()->minValue(2)->maxValue(10)->placeholder('5'),
                        Select::make('shape')->label(self::field('shape'))->placeholder(self::field('shape_star'))->options([
                            'star' => self::field('shape_star'),
                            'heart' => self::field('shape_heart'),
                            'thumb' => self::field('shape_thumb'),
                        ]),
                    ]),
                ]),
            QuestionType::make('opinion_scale', Questions\OpinionScale::class)->previewView(self::preview('scale'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)->group('scale')->answerType(AnswerType::Number)
                ->settingsSchema(fn (): array => [
                    Grid::make(BuilderSchema::columns(2))->schema([
                        TextInput::make('start')->label(self::field('start'))->integer()->minValue(0)->maxValue(1)->placeholder('0'),
                        TextInput::make('end')->label(self::field('end'))->integer()->minValue(2)->maxValue(10)->placeholder('10'),
                    ]),
                    ...self::scaleLabels(),
                ]),
            QuestionType::make('nps', Questions\Nps::class)->previewView(self::preview('scale'))
                ->icon(Heroicon::OutlinedChartBar)->group('scale')->answerType(AnswerType::Number)
                ->settingsSchema(fn (): array => self::scaleLabels()),

            QuestionType::make('date', Questions\DateQuestion::class)->previewView(self::preview('segments'))
                ->icon(Heroicon::OutlinedCalendarDays)->group('rich')->answerType(AnswerType::Date)
                ->settingsSchema(fn (): array => [
                    Grid::make(BuilderSchema::columns(3))->schema([
                        Select::make('format')->label(self::field('format'))->placeholder('DD / MM / YYYY')->options([
                            'DMY' => 'DD / MM / YYYY',
                            'MDY' => 'MM / DD / YYYY',
                            'YMD' => 'YYYY / MM / DD',
                        ]),
                        TextInput::make('min_date')->label(self::field('min_date'))->type('date'),
                        TextInput::make('max_date')->label(self::field('max_date'))->type('date'),
                    ]),
                    Grid::make(BuilderSchema::columns(2))->schema([
                        Toggle::make('calendar')->label(self::field('calendar'))->default(true),
                        Toggle::make('dropdowns')->label(self::field('dropdowns'))->default(true),
                    ]),
                ]),
            QuestionType::make('time', Questions\TimeQuestion::class)->previewView(self::preview('segments'))
                ->icon(Heroicon::OutlinedClock)->group('rich')
                ->settingsSchema(fn (): array => [
                    Grid::make(BuilderSchema::columns(2))->schema([
                        Select::make('twelve_hour')->label(self::field('clock'))->placeholder(self::field('clock_locale'))->options([
                            1 => self::field('clock_12'),
                            0 => self::field('clock_24'),
                        ]),
                        Toggle::make('dropdowns')->label(self::field('dropdowns'))->default(true)->inline(false),
                    ]),
                ]),
            QuestionType::make('contact_info', Questions\ContactInfo::class)->previewView(self::preview('composite'))
                ->icon(Heroicon::OutlinedIdentification)->group('rich')->answerType(AnswerType::Other)
                ->settingsSchema(fn (): array => self::partSettings(['first_name', 'last_name', 'email', 'phone', 'company'])),
            QuestionType::make('address', Questions\Address::class)->previewView(self::preview('composite'))
                ->icon(Heroicon::OutlinedMapPin)->group('rich')->answerType(AnswerType::Other)
                ->settingsSchema(fn (): array => self::partSettings(['line1', 'line2', 'city', 'state', 'postal_code', 'country'])),
            QuestionType::make('matrix', Questions\Matrix::class)->previewView(self::preview('matrix'))
                ->icon(Heroicon::OutlinedTableCells)->group('rich')->answerType(AnswerType::Other)
                ->settingsSchema(fn (): array => [
                    self::options('rows'),
                    self::options('answers'),
                    Toggle::make('multiple')->label(self::field('matrix_multiple')),
                ]),
            QuestionType::make('ranking', Questions\Ranking::class)->previewView(self::preview('ranking'))
                ->icon(Heroicon::OutlinedArrowsUpDown)->group('rich')->answerType(AnswerType::Other)
                ->settingsSchema(fn (): array => [self::options()]),
            QuestionType::make('file_upload', Questions\FileUpload::class)->previewView(self::preview('file-upload'))
                ->icon(Heroicon::OutlinedArrowUpTray)->group('rich')->answerType(AnswerType::Other)
                ->makeUsing(fn (string $key): Questions\FileUpload => Questions\FileUpload::make($key)
                    ->disk(config('conversational-forms.uploads.disk'))
                    ->directory(config('conversational-forms.uploads.directory'))
                    ->visibility(config('conversational-forms.uploads.visibility', 'private')))
                ->settingsSchema(fn (): array => [
                    Grid::make(BuilderSchema::columns(3))->schema([
                        Toggle::make('multiple')->label(self::field('multiple_files'))->inline(false),
                        TextInput::make('max_files')->label(self::field('max_files'))->integer()->minValue(1)->visibleJs(<<<'JS'
                            $get('multiple')
                            JS),
                        TextInput::make('max_size')->label(self::field('max_size'))->integer()->minValue(1)->suffix('KB'),
                    ]),
                    TagsInput::make('accepted_types')->label(self::field('accepted_types'))->placeholder('image/*, application/pdf'),
                ]),
            QuestionType::make('audio_answer', Questions\AudioAnswer::class)->previewView(self::preview('media'))
                ->icon(Heroicon::OutlinedMicrophone)->group('rich')->answerType(AnswerType::Other)
                ->makeUsing(fn (string $key): Questions\AudioAnswer => self::storeUploads(Questions\AudioAnswer::make($key)))
                ->settingsSchema(fn (): array => self::mediaSettings(defaultDuration: 120, defaultSize: 5 * 1024)),
            QuestionType::make('video_answer', Questions\VideoAnswer::class)->previewView(self::preview('media'))
                ->icon(Heroicon::OutlinedVideoCamera)->group('rich')->answerType(AnswerType::Other)
                ->makeUsing(fn (string $key): Questions\VideoAnswer => self::storeUploads(Questions\VideoAnswer::make($key)))
                ->settingsSchema(fn (): array => self::mediaSettings(defaultDuration: 60, defaultSize: 12 * 1024)),
            QuestionType::make('signature', Questions\Signature::class)->previewView(self::preview('signature'))
                ->icon(Heroicon::OutlinedPencil)->group('rich')->answerType(AnswerType::Other)
                ->makeUsing(fn (string $key): Questions\Signature => Questions\Signature::make($key)
                    ->disk(config('conversational-forms.uploads.disk'))
                    ->directory(config('conversational-forms.uploads.directory') . '/signatures'))
                ->settingsSchema(fn (): array => [
                    Toggle::make('typing')->label(self::field('typing'))->default(true),
                ]),
        ];
    }

    /**
     * Store a builder-made question's files where the config says.
     *
     * @template TUpload of Questions\FileUpload
     *
     * @param  TUpload  $question
     * @return TUpload
     */
    private static function storeUploads(Questions\FileUpload $question): Questions\FileUpload
    {
        return $question
            ->disk(config('conversational-forms.uploads.disk'))
            ->directory(config('conversational-forms.uploads.directory'))
            ->visibility(config('conversational-forms.uploads.visibility', 'private'));
    }

    /**
     * @return array<Component>
     */
    private static function mediaSettings(int $defaultDuration, int $defaultSize): array
    {
        return [
            Grid::make(BuilderSchema::columns(3))->schema([
                TextInput::make('max_duration')->label(self::field('max_duration'))->integer()->minValue(5)->maxValue(600)->suffix('s')->placeholder((string) $defaultDuration),
                TextInput::make('max_size')->label(self::field('max_size'))->integer()->minValue(100)->suffix('KB')->placeholder((string) $defaultSize),
                Toggle::make('allow_upload')->label(self::field('allow_upload'))->default(true)->inline(false),
            ]),
        ];
    }

    /**
     * @return array<Component>
     */
    private static function textSettings(): array
    {
        return [
            self::placeholder(),
            Grid::make(BuilderSchema::columns(2))->schema([
                TextInput::make('min_length')->label(self::field('min_length'))->integer()->minValue(0),
                TextInput::make('max_length')->label(self::field('max_length'))->integer()->minValue(1),
            ]),
        ];
    }

    private static function placeholder(): TextInput
    {
        return TextInput::make('placeholder')->label(self::field('placeholder'));
    }

    /**
     * A list of `{value, label}` options. A blank value is made from the label.
     */
    private static function options(string $name = 'options', bool $withImages = false): Repeater
    {
        return Repeater::make($name)
            ->label(self::field(($name === 'rows') ? 'rows_list' : $name))
            ->schema([
                Grid::make(BuilderSchema::columns($withImages ? 3 : 2))->schema([
                    // Options left empty are dropped when the draft is saved, so adding one never blocks saving.
                    TextInput::make('label')->label(self::field('option_label')),
                    TextInput::make('value')->label(self::field('option_value'))->placeholder(self::field('option_value_placeholder'))->alphaDash(),
                    ...($withImages ? [
                        FileUploadField::make('image')
                            ->label(self::field('option_image'))
                            ->image()
                            ->disk(config('conversational-forms.images.disk', 'public'))
                            ->directory(config('conversational-forms.images.directory'))
                            ->visibility('public')
                            // The builder's preview needs the uploaded image's address from the server.
                            ->live(),
                    ] : []),
                ]),
            ])
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->reorderable()
            ->collapsible()
            ->minItems(1)
            ->defaultItems(2)
            ->addActionLabel(self::field('add_option'));
    }

    /**
     * @return array<Component>
     */
    private static function choiceSettings(): array
    {
        return [
            Grid::make(BuilderSchema::columns(3))->schema([
                Toggle::make('multiple')->label(self::field('multiple'))->live(),
                Toggle::make('other')->label(self::field('other')),
                Toggle::make('randomize')->label(self::field('randomize')),
            ]),
            Grid::make(BuilderSchema::columns(2))->schema([
                TextInput::make('min_selections')->label(self::field('min_selections'))->integer()->minValue(1),
                TextInput::make('max_selections')->label(self::field('max_selections'))->integer()->minValue(1),
            ])->visible(fn (Get $get): bool => (bool) $get('multiple')),
        ];
    }

    /**
     * @return array<Component>
     */
    private static function yesNoLabels(): array
    {
        return [
            Grid::make(BuilderSchema::columns(2))->schema([
                TextInput::make('yes_label')->label(self::field('yes_label')),
                TextInput::make('no_label')->label(self::field('no_label')),
            ]),
        ];
    }

    /**
     * @return array<Component>
     */
    private static function scaleLabels(): array
    {
        return [
            Grid::make(BuilderSchema::columns(2))->schema([
                TextInput::make('start_label')->label(self::field('start_label')),
                TextInput::make('end_label')->label(self::field('end_label')),
            ]),
        ];
    }

    /**
     * @param  list<string>  $parts
     * @return array<Component>
     */
    private static function partSettings(array $parts): array
    {
        $options = array_combine($parts, array_map(fn (string $part): string => __("conversational-forms::conversational-forms.parts.{$part}"), $parts));

        return [
            Select::make('parts')->label(self::field('parts'))->multiple()->options($options)->placeholder(self::field('all_parts')),
            Select::make('required_parts')->label(self::field('required_parts'))->multiple()->options($options)->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: self::help('required_parts')),
        ];
    }

    private static function preview(string $name): string
    {
        return "conversational-forms::builder.editor.previews.{$name}";
    }

    private static function field(string $name): string
    {
        return __("conversational-forms::conversational-forms.builder.settings.{$name}");
    }

    private static function help(string $name): string
    {
        return __("conversational-forms::conversational-forms.builder.help.{$name}");
    }
}
