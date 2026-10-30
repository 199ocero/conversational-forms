<?php

namespace JaOcero\ConversationalForms\Definitions;

use BackedEnum;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use JaOcero\ConversationalForms\Contracts\ConversationQuestion;
use JaOcero\ConversationalForms\Logic\Enums\AnswerType;

/**
 * A question that can be stored in a definition and added in the builder:
 * the question class, its name in definitions (`short_text`), how it looks
 * in the builder and which settings it offers there.
 *
 *     QuestionType::make('payment', Payment::class)
 *         ->label('Payment')
 *         ->icon(Heroicon::OutlinedCreditCard)
 *         ->settingsSchema(fn (): array => [TextInput::make('amount')->numeric()]);
 *
 * The question class serializes its own settings (`getDefinitionSettings()`
 * and `definitionSettings()`); the builder fields here edit the same keys.
 */
class QuestionType
{
    protected ?string $label = null;

    protected string | BackedEnum | null $icon = null;

    protected AnswerType | Closure $answerType = AnswerType::Text;

    protected ?Closure $settingsSchema = null;

    protected ?Closure $makeUsing = null;

    protected string $group = 'other';

    /**
     * @param  class-string<Field&ConversationQuestion>  $questionClass
     */
    final public function __construct(protected string $name, protected string $questionClass) {}

    /**
     * @param  class-string<Field&ConversationQuestion>  $questionClass
     */
    public static function make(string $name, string $questionClass): static
    {
        return new static($name, $questionClass);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string | BackedEnum | null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * The kind of answer, which decides the operators offered in the builder.
     * A closure receives the question's settings, for types like multiple
     * choice whose answer depends on them.
     *
     * @param  AnswerType | Closure(array<string, mixed>): AnswerType  $type
     */
    public function answerType(AnswerType | Closure $type): static
    {
        $this->answerType = $type;

        return $this;
    }

    /**
     * Builder fields for the question's settings. Their names are the keys of
     * `settings` in the definition.
     *
     * @param  Closure(): array<Component>  $schema
     */
    public function settingsSchema(Closure $schema): static
    {
        $this->settingsSchema = $schema;

        return $this;
    }

    /**
     * Create the question differently, for example to set a storage disk.
     *
     * @param  Closure(string $key): (Field&ConversationQuestion)  $callback
     */
    public function makeUsing(Closure $callback): static
    {
        $this->makeUsing = $callback;

        return $this;
    }

    /**
     * The section of the builder's block picker: `text`, `choice`, `scale`,
     * `rich` or `other`.
     */
    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return class-string<Field&ConversationQuestion>
     */
    public function getQuestionClass(): string
    {
        return $this->questionClass;
    }

    public function getLabel(): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        // Built-in types are translated when shown, in the current locale.
        $key = "conversational-forms::conversational-forms.builder.types.{$this->name}";

        return trans()->has($key) ? __($key) : str($this->name)->replace('_', ' ')->ucfirst()->toString();
    }

    public function getIcon(): string | BackedEnum | null
    {
        return $this->icon;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function getAnswerType(array $settings = []): AnswerType
    {
        return ($this->answerType instanceof Closure) ? ($this->answerType)($settings) : $this->answerType;
    }

    /**
     * @return array<Component>
     */
    public function getSettingsSchema(): array
    {
        return ($this->settingsSchema instanceof Closure) ? ($this->settingsSchema)() : [];
    }

    public function makeQuestion(string $key): Field & ConversationQuestion
    {
        if ($this->makeUsing instanceof Closure) {
            return ($this->makeUsing)($key);
        }

        return $this->questionClass::make($key);
    }
}
