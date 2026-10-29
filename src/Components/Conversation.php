<?php

namespace JaOcero\ConversationalForms\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Livewire\Partials\PartialsComponentHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use JaOcero\ConversationalForms\Logic\ConversationPath;
use JaOcero\ConversationalForms\Logic\LogicGraph;
use JaOcero\ConversationalForms\Logic\PathResolver;
use JaOcero\ConversationalForms\Logic\Rule;
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\WelcomeScreen;

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

    protected string | Closure $submitMethod = 'submitConversation';

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
     * Every child of a conversation is wrapped in a `ConversationStep`, unless it already is one.
     */
    public function childComponents(array | Schema | Component | Action | ActionGroup | string | Htmlable | Closure | null $components, string $key = 'default'): static
    {
        if ($key !== 'default') {
            return parent::childComponents($components, $key);
        }

        $this->questions = $components;

        return parent::childComponents(fn (): array => $this->wrapInSteps(), $key);
    }

    /**
     * @return array<ConversationStep>
     */
    protected function wrapInSteps(): array
    {
        $components = $this->evaluate($this->questions) ?? [];

        if ($components instanceof Schema) {
            $components = $components->getComponents(withHidden: true);
        }

        return array_map(
            fn (mixed $component): ConversationStep => ($component instanceof ConversationStep)
                ? $component
                : ConversationStep::make([$component]),
            array_values(array_filter(is_array($components) ? $components : [$components], fn (mixed $component): bool => $component instanceof Component)),
        );
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
     * @return array<ConversationStep>
     */
    public function getSteps(): array
    {
        /** @var array<ConversationStep> */
        return array_values($this->getChildSchema()->getComponents(withActions: false, withHidden: true));
    }

    public function getStep(string $name): ?ConversationStep
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
        return array_map(fn (ConversationStep $step): string => $step->getName(), $this->getSteps());
    }

    public function getStepNumber(string $name): ?int
    {
        $index = array_search($name, $this->getStepNames(), strict: true);

        return is_int($index) ? ($index + 1) : null;
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
        $cacheKey = md5(serialize($answers));

        if (isset($this->resolvedPaths[$cacheKey])) {
            return $this->resolvedPaths[$cacheKey];
        }

        $steps = $this->getStepNames();
        $logic = $this->getLogic();

        if (! $this->hasValidatedLogic) {
            app(LogicGraph::class)->validate($steps, $logic);

            $this->hasValidatedLogic = true;
        }

        $stepFields = [];

        foreach ($this->getSteps() as $step) {
            $stepFields[$step->getName()] = $step->getFieldStatePaths();
        }

        return $this->resolvedPaths[$cacheKey] = app(PathResolver::class)->resolve($steps, $logic, $stepFields, $answers);
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

    public function getEnding(): string
    {
        return static::DEFAULT_ENDING;
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

        if (! $step instanceof ConversationStep) {
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

        return [
            'next' => $next,
            'ending' => ($next === null) ? $this->getEnding() : null,
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

    public function getWelcomeScreen(): ?WelcomeScreen
    {
        return $this->getScreen('welcome', WelcomeScreen::class);
    }

    public function getEndScreen(): ?EndScreen
    {
        return $this->getScreen('end', EndScreen::class);
    }
}
