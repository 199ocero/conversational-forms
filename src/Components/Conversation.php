<?php

namespace JaOcero\ConversationalForms\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Livewire\Partials\PartialsComponentHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use JaOcero\ConversationalForms\Contracts\ConversationQuestion;
use JaOcero\ConversationalForms\Contracts\FormatsAnswer;
use JaOcero\ConversationalForms\Logic\ConversationPath;
use JaOcero\ConversationalForms\Logic\LogicGraph;
use JaOcero\ConversationalForms\Logic\PathResolver;
use JaOcero\ConversationalForms\Logic\Rule;
use JaOcero\ConversationalForms\Questions\HiddenField;
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\WelcomeScreen;
use JaOcero\ConversationalForms\Support\Answer;
use JaOcero\ConversationalForms\Themes\Theme;

/**
 * A Typeform-style layout: shows its children one at a time.
 *
 * Alpine.js decides which step is visible. PHP validates each step, resolves
 * the path through the conversation from the answers, and is the only source
 * of truth on submit, so a modified client cannot skip a required question.
 */
class Conversation extends Component
{
    use Concerns\HasNavigation;
    use Concerns\HasScreens;

    public const DEFAULT_ENDING = 'default';

    protected string $view = 'conversational-forms::schemas.conversation';

    /**
     * @var array<Component | Action | ActionGroup | string | Htmlable> | Schema | Component | Action | ActionGroup | string | Htmlable | Closure | null
     */
    protected array | Schema | Component | Action | ActionGroup | string | Htmlable | Closure | null $questions = [];

    /**
     * Extra rules keyed by step name, so fields that are not `Question`s can have logic too.
     *
     * @var array<string, list<Rule>> | Closure
     */
    protected array | Closure $logic = [];

    protected bool | Closure $isFullScreen = false;

    /**
     * @var array<string, mixed> | Closure
     */
    protected array | Closure $variables = [];

    protected string | Closure $submitMethod = 'submitConversation';

    protected ?Closure $afterAdvance = null;

    /**
     * @var Theme | array<string, mixed> | Closure | null
     */
    protected Theme | array | Closure | null $theme = null;

    /**
     * @var array<string, ConversationPath>
     */
    protected array $resolvedPaths = [];

    protected bool $hasValidatedLogic = false;

    /**
     * @param  array<Component> | Closure  $components
     */
    final public function __construct(array | Closure $components = [])
    {
        $this->components($components);
    }

    /**
     * @param  array<Component> | Closure  $components
     */
    public static function make(array | Closure $components = []): static
    {
        $static = app(static::class, ['components' => $components]);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->key('conversation');

        $this->endScreen(EndScreen::make());
    }

    /**
     * Every child of a conversation is wrapped in a `ConversationStep`, unless it
     * already is a step. Hidden fields are kept in a schema of their own, since
     * they are never shown as a screen.
     */
    public function childComponents(array | Schema | Component | Action | ActionGroup | string | Htmlable | Closure | null $components, string $key = 'default'): static
    {
        if ($key !== 'default') {
            return parent::childComponents($components, $key);
        }

        $this->questions = $components;

        parent::childComponents(fn (): array => $this->getHiddenFields(), 'hidden');

        return parent::childComponents(fn (): array => $this->wrapInSteps(), $key);
    }

    /**
     * @return array<Step>
     */
    protected function wrapInSteps(): array
    {
        return array_map(
            fn (Component $component): Step => ($component instanceof Step)
                ? $component
                : ConversationStep::make([$component]),
            array_values(array_filter(
                $this->getQuestionComponents(),
                fn (Component $component): bool => ! $component instanceof HiddenField,
            )),
        );
    }

    /**
     * @return array<HiddenField>
     */
    public function getHiddenFields(): array
    {
        return array_values(array_filter(
            $this->getQuestionComponents(),
            fn (Component $component): bool => $component instanceof HiddenField,
        ));
    }

    /**
     * @return array<Component>
     */
    protected function getQuestionComponents(): array
    {
        $components = $this->evaluate($this->questions) ?? [];

        if ($components instanceof Schema) {
            $components = $components->getComponents(withHidden: true);
        }

        return array_values(array_filter(
            is_array($components) ? $components : [$components],
            fn (mixed $component): bool => $component instanceof Component,
        ));
    }

    /**
     * @param  array<string, list<Rule>> | Closure  $logic
     */
    public function logic(array | Closure $logic): static
    {
        $this->logic = $logic;

        return $this;
    }

    /**
     * The conversation's colours, font and background. Without a theme it
     * follows the Filament panel's colours and dark mode.
     *
     * @param  Theme | array<string, mixed> | Closure | null  $theme  A `Theme`, or one as an array (`Theme::toArray()`).
     */
    public function theme(Theme | array | Closure | null $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getTheme(): ?Theme
    {
        $theme = $this->evaluate($this->theme);

        return is_array($theme) ? (filled($theme) ? Theme::fromArray($theme) : null) : $theme;
    }

    /**
     * Run after each step is answered and validated, for example to save
     * the answers so far. The callback can receive `$step` (the step just
     * answered), `$path` (the `ConversationPath`) and `$livewire`.
     */
    public function afterAdvance(?Closure $callback): static
    {
        $this->afterAdvance = $callback;

        return $this;
    }

    /**
     * Variables and their starting values, for example `['score' => 0]`.
     * Variables are changed by logic (`add()`, `set()`, …) and are only ever
     * calculated on the server. Undeclared variables start at 0.
     *
     * @param  array<string, mixed> | Closure  $variables
     */
    public function variables(array | Closure $variables): static
    {
        $this->variables = $variables;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInitialVariables(): array
    {
        return $this->evaluate($this->variables);
    }

    /**
     * Full-screen conversations listen for keyboard shortcuts on the whole
     * window. Embedded ones only react while focus is inside them.
     */
    public function fullScreen(bool | Closure $condition = true): static
    {
        $this->isFullScreen = $condition;

        return $this;
    }

    public function isFullScreen(): bool
    {
        return (bool) $this->evaluate($this->isFullScreen);
    }

    /**
     * The Livewire method called once the last step is answered.
     */
    public function submitUsing(string | Closure $method): static
    {
        $this->submitMethod = $method;

        return $this;
    }

    public function getSubmitMethod(): string
    {
        return $this->evaluate($this->submitMethod);
    }

    /**
     * @return array<Step>
     */
    public function getSteps(): array
    {
        /** @var array<Step> */
        return array_values($this->getChildSchema()->getComponents(withActions: false, withHidden: true));
    }

    public function getStep(string $name): ?Step
    {
        foreach ($this->getSteps() as $step) {
            if ($step->getName() === $name) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function getStepNames(): array
    {
        return array_map(fn (Step $step): string => $step->getName(), $this->getSteps());
    }

    /**
     * The position of a step among the numbered steps (statements are not numbered).
     */
    public function getStepNumber(string $name): ?int
    {
        $number = 0;

        foreach ($this->getSteps() as $step) {
            if (! $step->isNumbered()) {
                continue;
            }

            $number++;

            if ($step->getName() === $name) {
                return $number;
            }
        }

        return null;
    }

    /**
     * Rules given to the conversation with `logic()`, keyed by step, without
     * the rules attached to the questions themselves.
     *
     * @return array<string, list<Rule>>
     */
    public function getConversationLogic(): array
    {
        return array_map(array_values(...), $this->evaluate($this->logic));
    }

    /**
     * @return array<string, list<Rule>>
     */
    public function getLogic(): array
    {
        $logic = $this->evaluate($this->logic);

        foreach ($this->getSteps() as $step) {
            $stepLogic = $step->getLogic();

            if ($stepLogic === []) {
                continue;
            }

            $logic[$step->getName()] = [...$stepLogic, ...($logic[$step->getName()] ?? [])];
        }

        return $logic;
    }

    /**
     * Resolve the path through the conversation from the current answers. The
     * result is memoized per set of answers, since visibility checks call this
     * once per step.
     */
    public function resolvePath(): ConversationPath
    {
        $answers = $this->getAnswers();
        $cacheKey = md5((string) json_encode(static::toCacheableAnswers($answers)));

        if (isset($this->resolvedPaths[$cacheKey])) {
            return $this->resolvedPaths[$cacheKey];
        }

        $steps = $this->getStepNames();
        $logic = $this->getLogic();
        $endings = array_keys($this->getEndScreens());

        if (! $this->hasValidatedLogic) {
            app(LogicGraph::class)->validate($steps, $logic, $endings);

            $this->hasValidatedLogic = true;
        }

        $stepFields = [];
        $stepConditions = [];

        foreach ($this->getSteps() as $step) {
            $stepFields[$step->getName()] = $step->getFieldStatePaths();

            if ($conditions = $step->getVisibilityConditions()) {
                $stepConditions[$step->getName()] = $conditions;
            }
        }

        return $this->resolvedPaths[$cacheKey] = app(PathResolver::class)->resolve(
            steps: $steps,
            rules: $logic,
            stepFields: $stepFields,
            answers: $answers,
            stepConditions: $stepConditions,
            variables: $this->getInitialVariables(),
            defaultEnding: $endings[0] ?? static::DEFAULT_ENDING,
        );
    }

    /**
     * Answers can contain objects such as uploaded files, which cannot be
     * serialized, so they are described by their class and identity instead.
     */
    protected static function toCacheableAnswers(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => array_map(static::toCacheableAnswers(...), $value),
            is_object($value) => $value::class . '#' . spl_object_id($value),
            default => $value,
        };
    }

    public function isStepOnPath(string $name): bool
    {
        return $this->resolvePath()->contains($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnswers(): array
    {
        return (array) $this->getContainer()->getRawState();
    }

    /**
     * The end screen the current answers lead to.
     */
    public function getEnding(): string
    {
        return $this->resolvePath()->ending ?? static::DEFAULT_ENDING;
    }

    /**
     * Validate the current step and tell the browser where to go next.
     *
     * Returns `null` when the step is invalid (its errors are rendered) or when
     * the step is not on the path, which only happens with a stale or modified
     * client. The response never includes answers.
     *
     * @return array{next: ?string, ending: ?string, path: list<string>, progress: float}|null
     */
    #[Exposed]
    public function advance(string $fromStep): ?array
    {
        $livewire = $this->getLivewire();
        $partials = app(PartialsComponentHook::class);
        $step = $this->resolvePath()->contains($fromStep) ? $this->getStep($fromStep) : null;

        if (! $step instanceof Step) {
            $partials->skipPartialRender($livewire);

            return null;
        }

        $hadErrors = $livewire->getErrorBag()->isNotEmpty();

        try {
            $step->getChildSchema()->validate();
        } catch (ValidationException $exception) {
            // Only this step is re-rendered to show its errors.
            $step->partiallyRender();

            throw $exception;
        }

        // Every step is already in the DOM, so a valid answer needs no re-render,
        // unless error messages from an earlier attempt have to be cleared.
        $hadErrors ? $step->partiallyRender() : $partials->skipPartialRender($livewire);

        $path = $this->resolvePath();
        $next = $path->after($fromStep);

        if ($this->afterAdvance instanceof Closure) {
            $this->evaluate($this->afterAdvance, ['step' => $fromStep, 'path' => $path]);
        }

        return [
            'next' => $next,
            'ending' => ($next === null) ? $path->ending : null,
            'path' => $path->toArray(),
            'progress' => $path->progressAfter($fromStep),
        ];
    }

    /**
     * The step that contains a field, for moving the respondent to an error after submitting.
     */
    public function getStepNameForStatePath(string $statePath): ?string
    {
        foreach ($this->getSteps() as $step) {
            foreach ($step->getFields() as $field) {
                $fieldStatePath = $field->getStatePath();

                if (($statePath === $fieldStatePath) || str_starts_with($statePath, "{$fieldStatePath}.")) {
                    return $step->getName();
                }
            }
        }

        return null;
    }

    /**
     * Find a field in the conversation by its name (its state path relative to the form).
     */
    public function getField(string $name): ?Field
    {
        $containerStatePath = $this->getContainer()->getStatePath();
        $statePath = filled($containerStatePath) ? "{$containerStatePath}.{$name}" : $name;

        $fields = [
            ...array_merge(...array_map(fn (Step $step): array => $step->getFields(), $this->getSteps()) ?: [[]]),
            ...array_values($this->getChildSchema('hidden')?->getFlatFields(withHidden: true) ?? []),
        ];

        foreach ($fields as $field) {
            if ($field->getStatePath() === $statePath) {
                return $field;
            }
        }

        return null;
    }

    /**
     * The absolute Livewire state path of an answer, for reading it in the browser.
     */
    public function getAnswerStatePath(string $name): string
    {
        $containerStatePath = $this->getContainer()->getStatePath();

        return filled($containerStatePath) ? "{$containerStatePath}.{$name}" : $name;
    }

    /**
     * Labels for an answer's possible values, so piping shows "Enterprise" rather than "enterprise".
     *
     * @return array<string, string>|null
     */
    public function getAnswerLabels(string $name): ?array
    {
        $field = $this->getField($name);

        return ($field instanceof ConversationQuestion) ? $field->getAnswerLabels() : null;
    }

    public function formatAnswer(string $name): string
    {
        $answer = data_get($this->getAnswers(), $name);
        $field = $this->getField($name);

        return ($field instanceof FormatsAnswer) ? $field->formatAnswer($answer) : Answer::format($answer);
    }

    public function getWelcomeScreen(): ?WelcomeScreen
    {
        return $this->getScreen('welcome', WelcomeScreen::class);
    }

    /**
     * @return array<string, EndScreen> End screens keyed by name. The first is the default.
     */
    public function getEndScreens(): array
    {
        $screens = [];

        foreach ($this->getChildSchema('end')?->getComponents(withActions: false) ?? [] as $component) {
            if ($component instanceof EndScreen) {
                $screens[$component->getName()] = $component;
            }
        }

        return $screens;
    }

    public function getEndScreen(?string $name = null): ?EndScreen
    {
        $screens = $this->getEndScreens();

        return ($name === null) ? (array_values($screens)[0] ?? null) : ($screens[$name] ?? null);
    }
}
