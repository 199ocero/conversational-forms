# Conversational Forms: Implementation Plan

> `jaocero/conversational-forms`: Typeform-style conversational forms for Filament. One question at a time, fully in your Laravel app.

| | |
|---|---|
| Repo | `199ocero/conversational-forms` |
| Package | `jaocero/conversational-forms` |
| Namespace | `JaOcero\ConversationalForms` |
| License | MIT |
| Targets | PHP 8.2+, Laravel 12 / 13, Filament ^5.0 (Livewire ^4.0), Tailwind CSS v4, Alpine.js |
| Dev host | `filament-playground/packages/conversational-forms` (symlinked path repository) |

## 0. Context

Filament's `Wizard` gives multi-step forms, but not the conversational experience people know from Typeform: one question per screen, big type, instant keyboard-driven flow, auto-advance, letter-key choices, logic jumps, multiple endings and a shareable public URL. This plugin adds that experience natively. Answers still flow through Filament's normal pipeline (state, validation, `$get()`/`$set()`, `->visible()`, relationships and model saving), so a conversation is a Filament form and not a separate system.

It's a portfolio project (after `jaocero/radio-deck` and `jaocero/activity-timeline`), so the priorities are polish, clarity, tests and docs over speed.

### How this plan was researched

Facts below come from the installed Filament **v5.11.0** / Livewire **v4.4.8** source in the playground's `vendor/`, the docs bundled in vendor (`vendor/filament/forms/docs/22-custom-fields.md`, `vendor/filament/schemas/docs/09-custom-components.md`, `05-wizards.md`, `forms/docs/01-overview.md`), filamentphp.com 5.x docs (assets, plugins, installation), and the official `filamentphp/plugin-skeleton` `5.x` branch. Items marked **[verify]** are things I couldn't confirm from source and must be proven in the Phase 0 spikes. I didn't guess them.

### Filament v5 facts the architecture relies on

| Fact | Source |
|---|---|
| `Wizard` tracks the current step **in Alpine** (`step` property), validates server-side via `#[Exposed] nextStep($currentStepIndex, $currentStepKey)`, which runs `$currentStep->getChildSchema()->validate()` inside `callBeforeValidation()`/`callAfterValidation()`, then `dispatch('next-wizard-step', key:, step:, currentStep:)`. Back navigation is client-only. | `vendor/filament/schemas/src/Components/Wizard.php:96-157`, `schemas/resources/js/components/wizard.js` |
| Public methods marked `#[Exposed]` (`Filament\Support\Components\Attributes\Exposed`) are callable from Alpine as `this.$methodName({ arg: … })`, or via `$callSchemaComponentMethod()`. They are routed through `InteractsWithSchemas::callSchemaComponentMethod()`; the promise resolves to the return value; `#[Renderless]` skips the re-render. | `schemas/src/Concerns/InteractsWithSchemas.php:89`, custom-fields docs §"Calling field methods from JavaScript" |
| Field views bind with `$applyStateBindingModifiers('wire:model')` or `$wire.$entangle(...)`; `$getFieldWrapperView()` is the standard wrapper. If you replace the wrapper you must preserve the label/helper/error IDs, merge `getAccessibilityAttributes()`, and keep the hidden `data-field-accessibility` element. | `forms/stubs/FieldView.stub`, forms overview docs §"Preserving accessibility in custom field views" |
| Client-side reactivity exists natively: `visibleJs()` / `hiddenJs()`, `afterStateUpdatedJs()`, `JsContent` labels, and a JS `$get()` / `$set()`. | forms overview docs §"Hiding a field using JavaScript", §"Using JavaScript to determine text content" |
| Hidden components are not rendered, not validated and not dehydrated, unless `dehydratedWhenHidden()`. | `schemas/src/Components/Concerns/HasState.php:269,814-845` |
| Assets: `FilamentAsset::register([...], package: 'jaocero/conversational-forms')`; `AlpineComponent` loaded with `x-load` + `x-load-src="FilamentAsset::getAlpineComponentSrc(id, package)"`; `Css::make()->loadedOnRequest()` loaded with `x-load-css`; `php artisan filament:assets` publishes to `public/{css,js}/jaocero/conversational-forms/`. A plugin that must work **without a custom theme** should ship a compiled stylesheet. | filamentphp.com/docs/5.x/advanced/assets |
| v5 also offers `JsField` / `HasJsRenderer` (React/Vue/Svelte/vanilla renderers). We **don't** use it: Blade + Alpine is lighter, matches the Filament ecosystem and the author's other plugins. | custom-fields docs §"Rendering fields with JavaScript frameworks" |
| Panel plugins implement `Filament\Contracts\Plugin` (`getId`, `register`, `boot`, `make`, `get`). Panels expose `routes()`, `authenticatedRoutes()`. `SimplePage` uses layout `filament-panels::components.layout.simple`. | skeleton `src/SkeletonPlugin.php`, `filament/src/Panel/Concerns/HasRoutes.php:76`, `filament/src/Pages/SimplePage.php` |
| Testing helpers available via Livewire `Testable`: `fillForm`, `assertHasFormErrors`, `assertSchemaStateSet`, `assertSchemaComponentVisible/Hidden`, `goToNextWizardStep`, `assertWizardCurrentStep`, etc. The skeleton shows `Testable::mixin(new TestsSkeleton)` for plugin-provided helpers. | `forms/src/Testing/TestsForms.php`, `schemas/src/Testing/TestsSchemas.php` |
| Requirements: PHP 8.2+, Laravel 11.28+, Livewire 4.0+, Tailwind 4.0+ (4.1+ for themes). | filamentphp.com/docs/5.x/introduction/installation, upgrade guide |
| Skeleton 5.x tooling: Pest ^3.7\|^4, Testbench ^9\|^10\|^11, Larastan ^3, Rector ^2, Pint, esbuild ^0.28; CI matrix PHP 8.2–8.4 × Laravel 11/12/13 (excluding L13 + PHP 8.2), lowest/stable, ubuntu + windows. | `plugin-skeleton@5.x` `composer.json`, `.github/workflows/tests.yml` |

---

## 1. Architecture overview

### 1.1 The big picture

```
                          ┌──────────────────────────────────────────────┐
  Developer code ───────► │ Schema (Livewire component with HasSchemas)  │
                          │  └─ Conversation (layout component)          │
                          │      ├─ WelcomeScreen                        │
                          │      ├─ ConversationStep ◄─ auto-wrapped     │
                          │      │    └─ ShortText (our Question)        │
                          │      ├─ ConversationStep                     │
                          │      │    └─ Select (any Filament field)     │
                          │      ├─ QuestionGroup (a step w/ many fields)│
                          │      └─ EndScreen[]                          │
                          └───────────────┬──────────────────────────────┘
                                          │ uses
            ┌─────────────────────────────┼──────────────────────────────┐
            ▼                             ▼                              ▼
   Logic engine (pure PHP)       Alpine `conversation`          InteractsWithConversation
   Rule / Condition / Operator   component (step stage,         (Livewire trait: submit,
   PathResolver → path, ending,  keyboard, transitions,         result, end screen,
   variables (authoritative)     focus, progress, a11y)         redirect)
            │                             ▲                              │
            └── JSON rule spec ───────────┘ (JS evaluator: parity,      ▼
                                             previews; v0.3)     ConversationPage (public,
                                                                 panel-less) → later the
                                                                 Panel plugin + Builder
```

**Principle: the server is authoritative, the client is fast.** Alpine owns *which screen is visible* and every visual/keyboard concern. PHP owns *validation, the path through the form, the ending, and computed variables*. Every forward move is a single Livewire round trip that validates the current step and returns the next step from a deterministic path resolver. On submit the whole path is recomputed from the answers, so a tampered client can't skip a required question or forge a score.

### 1.2 Main classes

| Class | Extends / implements | Responsibility |
|---|---|---|
| `Components\Conversation` | `Filament\Schemas\Components\Component` | Layout component. Holds steps, welcome screen, end screens, variables, settings (progress bar, navigation arrows, auto-advance delay, transition, full-screen mode). Wraps each plain child in a `ConversationStep`. Exposes `#[Exposed] advance()`. Injects path-based visibility into steps. Renders the Alpine stage. |
| `Components\ConversationStep` | `Component` (internal, like `Wizard\Step`) | One screen. Has its own child schema so that **only that step** can be validated (`getChildSchema()->validate()`, the exact Wizard technique). Not normally instantiated by users. |
| `Components\QuestionGroup` | `ConversationStep` | Public: several questions on one screen. |
| `Screens\WelcomeScreen`, `Screens\EndScreen`, `Screens\Statement` | `Component` | Non-field screens. `Statement` is a step; Welcome/End are slots on `Conversation`. `EndScreen` has a key so logic can route to it. |
| `Screens\Redirect` | `EndScreen` | An ending that redirects (with piped query params). |
| `Questions\Question` (abstract) | `Filament\Forms\Components\Field` + `Contracts\ConversationAware`, `Contracts\FormatsAnswer` | Base for all our fields: shared question chrome view, description, media, piping, auto-advance, keyboard hints, logic attachment. |
| `Questions\ShortText`, `LongText`, `Email`, `MultipleChoice`, `YesNo`, `OpinionScale`, … | `Question` | One class + Blade view (+ optional Alpine component) per field type. Reuse Filament concern traits where they fit (e.g. `CanBeLengthConstrained`, `HasPlaceholder`, `HasOptions`-style traits) so the validation rules match Filament's. |
| `Contracts\ConversationAware` | interface | Optional, for **any** field (incl. third-party): declares auto-advance and keyboard hints. |
| `Logic\Logic` | static builder | `Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size')`, `Logic::always()`, `Logic::any()`/`all()` groups. |
| `Logic\Rule`, `Condition`, `ConditionGroup`, `Enums\Operator`, `Enums\Combinator`, `Actions\*` | value objects (`Arrayable`, `JsonSerializable`) | Declarative, serializable logic. The same JSON is consumed by PHP, JS and the builder. |
| `Logic\Evaluator` | final class | Evaluates a condition tree against answers + variables. Pure, no Filament dependency (easy to unit test). |
| `Logic\PathResolver` | final class | Walks steps from the start applying rules → `ConversationPath` (ordered step keys, ending key, variables, jumps taken). Memoized per state hash. |
| `Logic\LogicGraph` | final class | Static analysis at build time: unknown targets, backward jumps (loops), unreachable steps, operator/type mismatches → `InvalidLogicException` with a precise message. Also feeds the builder's logic map. |
| `Livewire\Concerns\InteractsWithConversation` | trait for Livewire components | `submitConversation()`: validate the full path → `ConversationResult` (data, path, ending, variables) → `handleConversationSubmission()` hook → end screen / redirect. Works in plain Livewire components **and** Filament pages. |
| `Livewire\ConversationPage` | abstract `Livewire\Component` + `HasSchemas` + `InteractsWithSchemas` + `InteractsWithConversation` | Public, panel-less, full-page conversation with its own minimal layout. Users subclass it and define `conversation()`. |
| `Tables\Columns\AnswerColumn`, `Infolists\Components\AnswerEntry` (+ visual variants) | Filament `Column` / `Entry` | Display stored answers using the question's own `formatAnswer()` (so a choice shows its label, a rating shows stars). |
| `Testing\TestsConversations` | `Testable` mixin | `->advanceConversation()`, `->assertConversationStep('email')`, `->assertConversationPath([...])`, `->submitConversation()`, `->assertConversationEnding('qualified')`. |
| `ConversationalFormsServiceProvider` | `Spatie\LaravelPackageTools\PackageServiceProvider` | Views, translations, config, assets, Testable mixin, (later) migrations and commands. |
| `ConversationalFormsPlugin` (Phase 5+) | `Filament\Contracts\Plugin` | Panel integration: guest conversation route under a panel's theme, builder resource, responses resource. |

### 1.3 Directory structure

```
conversational-forms/
├── .github/
│   ├── workflows/ tests.yml · browser-tests.yml · js.yml · phpstan.yml · fix-php-code-style.yml · update-changelog.yml
│   ├── ISSUE_TEMPLATE/ (bug.yml, config.yml) · CONTRIBUTING.md · SECURITY.md · FUNDING.yml · dependabot.yml
├── art/                     banner.jpeg, demo.gif, screenshots (light/dark/mobile)
├── bin/build.js             esbuild (Alpine components) + Tailwind CLI (CSS) → resources/dist
├── config/conversational-forms.php
├── database/migrations/     *.php.stub (Phase 5+ only; v0.x ships no migrations)
├── docs/                    PLAN.md (internal planning only; user docs live in README.md)
├── resources/
│   ├── css/
│   │   ├── index.css        entry: tokens + components, compiled to dist
│   │   ├── tokens.css       --cf-* CSS variables (light + .dark)
│   │   ├── conversation.css stage, step, transitions, progress, nav, hints
│   │   └── questions/*.css  one file per question type
│   ├── dist/                committed build output (CSS + JS), checked in CI
│   ├── js/
│   │   ├── components/conversation.js              Alpine: stage controller
│   │   ├── components/questions/*.js               Alpine per field that needs JS
│   │   ├── keyboard/{shortcuts.js, guard.js}       key map + "should we handle this key?"
│   │   ├── logic/{evaluate.js, operators.js}       JS evaluator (parity with PHP, v0.3)
│   │   └── support/{focus.js, announce.js, motion.js}
│   ├── lang/en/conversational-forms.php
│   └── views/
│       ├── components/conversation.blade.php, step.blade.php, progress-bar.blade.php,
│       │             navigation.blade.php, question.blade.php (shared question chrome),
│       │             keyboard-hint.blade.php
│       ├── questions/short-text.blade.php … (one per field)
│       ├── screens/welcome-screen.blade.php, end-screen.blade.php, statement.blade.php
│       └── livewire/conversation-page.blade.php, layouts/public.blade.php
├── src/
│   ├── ConversationalFormsServiceProvider.php
│   ├── ConversationalFormsPlugin.php                (Phase 5)
│   ├── Components/  Conversation.php, ConversationStep.php, QuestionGroup.php
│   │   └── Concerns/ HasWelcomeScreen, HasEndScreens, HasProgressBar, HasNavigation,
│   │                 CanAutoAdvance, HasVariables, HasTransitions
│   ├── Screens/     WelcomeScreen.php, EndScreen.php, Statement.php, Redirect.php
│   ├── Questions/   Question.php, ShortText.php, LongText.php, Email.php, MultipleChoice.php,
│   │   │            YesNo.php, OpinionScale.php, … (later phases)
│   │   └── Concerns/ HasDescription, HasMedia, HasPipedLabel, HasLogic, HasLetterKeys,
│   │                 HasChoices, CanRandomizeOptions, HasOtherOption, HasScale
│   ├── Contracts/   ConversationAware.php, FormatsAnswer.php, CaptchaProvider.php (Phase 6)
│   ├── Logic/       Logic.php, Rule.php, Condition.php, ConditionGroup.php, Evaluator.php,
│   │   │            PathResolver.php, ConversationPath.php, LogicGraph.php, Piping.php
│   │   ├── Actions/ JumpTo.php, EndWith.php, Calculate.php, SetVariable.php
│   │   ├── Enums/   Operator.php, Combinator.php, CalculationOperator.php
│   │   └── Exceptions/InvalidLogicException.php
│   ├── Livewire/    ConversationPage.php, Concerns/InteractsWithConversation.php,
│   │                ConversationResult.php
│   ├── Support/     KeyboardHint.php, Media.php
│   ├── Tables/Columns/ AnswerColumn.php, RatingColumn.php, NpsColumn.php
│   ├── Infolists/Components/ AnswerEntry.php, ResponseEntry.php
│   ├── Testing/     TestsConversations.php
│   └── (Phase 5+) Definitions/ (FormDefinition, QuestionRegistry), Models/, Filament/Resources/
├── tests/
│   ├── Pest.php · TestCase.php · ArchTest.php
│   ├── Unit/Logic/ (Evaluator, PathResolver, LogicGraph, Operator datasets)
│   ├── Feature/ (Conversation, Questions/*, InteractsWithConversation, ConversationPage, Columns/Entries)
│   ├── Browser/ (keyboard, focus, transitions, a11y, mobile viewport)
│   ├── Fixtures/ (Livewire components, models, enums)
│   ├── fixtures/logic/*.json   shared PHP↔JS parity cases
│   └── js/ (Vitest: keyboard guard, evaluator parity, buffered digit typing)
├── composer.json · package.json · phpstan.neon.dist · phpunit.xml.dist · pint.json · rector.php · vitest.config.js
└── README.md · CHANGELOG.md · LICENSE.md
```

Namespaces: `Questions\` and `Screens\` (instead of radio-deck's `Forms\Components\`) keep imports short and self-describing (`use JaOcero\ConversationalForms\Questions\YesNo;`). View namespace `conversational-forms::`, asset package `jaocero/conversational-forms`, CSS hook prefix `cf-`, CSS variable prefix `--cf-`.

---

## 2. Key technical decisions and trade-offs

### 2.1 Step tracking: Alpine owns the screen, Livewire owns the truth

- **Alpine** (`resources/js/components/conversation.js`) holds `current` (step key), `history` (visited keys, for Back), `direction` (for the transition), `path` and `progress` (from the server), and `phase` (`welcome | steps | submitting | ended`).
- **Livewire/PHP** holds the answers (form state) and computes the path. There's no new public Livewire property: the path is always *derived* from answers by `PathResolver`, so nothing about navigation needs to be trusted from the client.
- **Back** is client-only (pop `history`), with no round trip, exactly like Wizard's `goToPreviousStep()`.
- **Forward** calls `#[Exposed] Conversation::advance(string $fromStep)` → returns `['next' => ?string, 'ending' => ?string, 'path' => string[], 'progress' => float]`.
- Optional `->persistStepInQueryString()` (mirrors Wizard) for deep links/refresh. On load the server verifies the requested step is on the current path and that every earlier step validates; otherwise it starts from the first invalid step.
- Rejected alternative: a `#[Locked] public string $currentStep` on the Livewire component, with every Back costing a round trip. That's slower, and it doesn't fit Filament's "components are not Livewire components" model.

### 2.2 Per-step validation

Each child of `Conversation` is wrapped in a `ConversationStep` that owns a child schema, so `advance()` does exactly what `Wizard::nextStep()` does:

```php
#[Exposed]
public function advance(string $fromStep): ?array
{
    $step = $this->getStepByKey($fromStep);
    $path = $this->resolvePath();                       // from current raw state

    if (! $step || ! $path->contains($fromStep)) {
        return null;                                    // tampered / stale client
    }

    try {
        $step->callBeforeValidation();
        $step->getChildSchema()->validate();            // only this screen's fields
        $step->callAfterValidation();
    } catch (Halt) {
        return null;
    }

    $path = $this->resolvePath(fresh: true);

    return $path->toAdvanceResponse(after: $fromStep);  // next | ending, path, progress
}
```

- A failed validation throws `ValidationException`. Livewire renders the errors and the Alpine stage stays put, focuses the first `[aria-invalid=true]` control and shakes the step (unless reduced motion is set). **Verified in spike 1:** the promise resolves to `null` (it only rejects for Livewire `#[Json]` methods), so `null` means "stay". See [spikes.md](spikes.md#spike-1-exposed-method-results-and-morph-timing-answered).
- **Client-side pre-checks** (required, email format, min/max selections) give instant feedback before the round trip, as a UX nicety only. The server always re-validates.
- **Skipped questions aren't required:** every `ConversationStep` gets an injected `->hidden(fn () => ! $conversation->isStepOnPath($key))`. Filament then neither validates nor dehydrates off-path steps on submit (`HasState::isHiddenAndNotDehydratedWhenHidden()`), which is native semantics with no custom validator. Users can opt into keeping answers with `dehydratedWhenHidden()`.

### 2.3 Rendering strategy and hidden steps

- **Every** step is rendered, including off-path ones (changed after spike 4). Filament's `hidden()` on off-path steps drives only validation and dehydration. A path change therefore never needs new DOM, so a successful `advance()` skips rendering entirely (0.6 kB response), and a failed one re-renders only that step via `$step->partiallyRender()` (4.6 kB), instead of a 232 kB full render for 41 questions. Steps carry a `cf-step-{name}` key so they can be partially rendered. The `$wire` promise resolves *after* the morph (spike 1).
- The stage root uses `wire:ignore.self` (as Wizard does) so morphs never clobber Alpine's classes. Every step has a stable `wire:key="{conversationKey}.{stepKey}"`.
- Inactive steps are present but `hidden` + `inert` (removing them from the tab order and the accessibility tree), and the active one animates in.
- **JS-heavy fields in hidden steps** (requirement 7): on every switch, the stage (a) dispatches `cf-step-shown` on `window` with `{ conversation, step, element }`, (b) sets `data-cf-active` on the step, and (c) after the transition ends, dispatches a synthetic `resize` (fixes canvas/editor/map libraries that measured a `display:none` container). Our own heavy fields (Signature, Matrix scrolling, Ranking) initialise lazily on first `cf-step-shown`. `->lazy()` on a step defers rendering the step's *content* until it's next on the path, for very heavy third-party fields.
- Performance (spike 4, 41 questions, in-process p50): full render 22.2 ms / 232 kB → step-only partial render 6.5 ms / 4.6 kB → skipped render 6.2 ms / 0.6 kB. The `advance()` body itself is under 0.4 ms.

### 2.4 Auto-advance: how completion is detected

Completion is **declared by the field, not guessed from state**:

1. A field implementing `ConversationAware` with `shouldAutoAdvance() === true` dispatches `cf-answered` (bubbling) from its root once its answer is complete. Examples: a single choice clicked or letter-keyed, Yes/No chosen, a scale value picked, a date's last box filled with a valid date.
2. The stage receives it, shows the confirmation micro-animation (the selected option blinks), waits `autoAdvanceDelay` (default 400 ms, `0` in reduced-motion or test mode), then calls `advance()`. A second interaction during the delay cancels and restarts it.
3. Fallback (non-`ConversationAware`) fields never auto-advance: they advance with Enter or the OK button. This is predictable and can't misfire mid-typing.
4. `->autoAdvance(false)` on the Conversation or a question disables it. It's never on for multi-select, text or uploads.

### 2.5 Keyboard architecture

- One `keydown` listener on the stage root (or on `window` in full-screen mode, i.e. `ConversationPage`). A **guard** (`keyboard/guard.js`, unit-tested) decides whether the stage may act. It refuses when `event.defaultPrevented`, when an IME is composing (`event.isComposing`), when the target is inside `[data-cf-keyboard-ignore]`, when a listbox/combobox/datepicker is open (`aria-expanded="true"`), or for Enter in a `textarea`/`contenteditable` without the right modifier.
- Fields own their own keys (letters, digits, arrows) by listening on their root and calling `preventDefault()` when they consume a key, so stage and field never fight.
- Fields advertise their shortcuts through `ConversationAware::getKeyboardHints()` → rendered as the "press **Enter ↵**" / "**A**" key badges and in an `aria-keyshortcuts` attribute.

### 2.6 Logic: one declarative spec, two evaluators

- Rules are **data**, not closures: `Rule { conditions: ConditionGroup, actions: Action[] }`, and they serialize to JSON. This one choice gives us builder storage, the logic map, JS evaluation and safety (no user JS is ever `eval`ed).
- **PHP evaluator is authoritative**: `advance()`, `isStepOnPath()`, submit, scores.
- **JS evaluator** (v0.3) mirrors it for instant UI: progress prediction while typing, client-side show/hide inside a `QuestionGroup` (compiled to Filament's own `visibleJs()` with values encoded via `Js::from()`), and piping preview. Parity is guaranteed by **shared JSON fixtures** in `tests/fixtures/logic/*.json`, run by Pest and by Vitest.
- Rule semantics (Typeform-compatible): rules attach to the question they follow. After a question is answered, its rules are evaluated in order: **all matching calculation actions apply; the first matching jump/end action wins**. `Logic::always()` is the explicit fallback. With no jump, flow continues to the next step in order.
- **Loop safety by construction:** jumps may only target a *later* step or an ending. `LogicGraph` throws `InvalidLogicException` at build time for backward jumps, unknown targets or unreachable endings. `PathResolver` also has a hard visit cap (`count(steps) + 1`) as belt-and-braces.
- Escape hatch: `Logic::whenUsing(fn (Get $get) => …)` for PHP-only conditions, which marks the rule `serverOnly`. It isn't serialized, isn't usable in the builder, and isn't evaluated client-side (documented trade-off).

### 2.7 Submission

The final step's Enter/OK runs `advance()` → `{ ending: 'qualified' }` → the client calls the Livewire submit method (default `submitConversation`, configurable via `Conversation::submitUsing('create')` for Filament pages). `InteractsWithConversation::submitConversation()`:

1. `$this->form->getState()` validates every **on-path** question (off-path ones are hidden) and dehydrates.
2. `PathResolver` recomputes path, ending and variables from the dehydrated answers. Client-sent values are never trusted.
3. Calls `handleConversationSubmission(array $data, ConversationResult $result): void`, the user's hook (save a model, send mail…). Model saving via `->model()`/relationships works as in any Filament form.
4. Dispatches `conversation-completed` → Alpine shows the routed `EndScreen`. A `Redirect` ending calls `$this->redirect()`.

**Data ownership (decided):** for code-defined conversations the plugin **never stores anything**. It ships no migrations or models for them and has no `storeResponses()` option. It validates the answers and hands back `$data` plus the `ConversationResult`, just like a normal Filament form, and the developer saves it to their own models and migrations. Plugin-owned tables exist only for the builder (Phase 5–6), where no developer code is there to receive the data.

### 2.8 Assets and CSS

- **JS**: Alpine components bundled by esbuild (same `bin/build.js` shape as radio-deck and the skeleton) → `resources/dist/components/*.js`, registered as `AlpineComponent::make('conversation', …)` etc., loaded on demand with `x-load` / `x-load-src`. Nothing loads on pages that don't use the plugin.
- **CSS** (decision: prebuilt): Blade views use **only semantic hook classes** (`cf-conversation`, `cf-step`, `cf-question-label`, `cf-choice`, `cf-choice-key`, …). Styles are authored in `resources/css/**` with Tailwind v4 (`@apply`, theme functions), compiled by the Tailwind CLI with utilities scoped to what our CSS uses and **no preflight**, into `resources/dist/conversational-forms.css`. Registered as `Css::make('conversational-forms', …)->loadedOnRequest()` and loaded with `x-load-css` on the conversation root. Rules live in `@layer components` so consumer utilities still override. Verified setup (spike 2): `@import 'tailwindcss/theme.css' layer(theme) theme(inline)` + `@source not inline('*')`, with fallbacks written as `--theme(--color-…)`; output is only our rules.
- **Theming** via CSS variables on `.cf-conversation`: `--cf-accent` (defaults to Filament `--primary-600` / `--primary-400` in dark), `--cf-bg`, `--cf-fg`, `--cf-muted`, `--cf-font-family`, `--cf-question-size`, `--cf-radius`, `--cf-choice-bg`, `--cf-transition-duration`, etc. PHP sugar: `Conversation::make()->accentColor(Color::Indigo)->font('Inter')->backgroundImage(...)`, which emits inline variables. Dark mode follows Filament's `.dark` ancestor class. The public layout also honours `prefers-color-scheme` unless forced.
- **Public page CSS**: the panel-less layout includes `@filamentStyles`, `@filamentScripts` and Filament's compiled default stylesheet (so fallback Filament fields look right without a Vite build), plus our CSS. Consumers with their own theme pass `->layout('layouts.app')`. Verified (spike 3): Filament's stylesheet is the `app` Theme asset, rendered outside a panel with `FilamentAsset::getTheme('app')?->getHtml()`.

### 2.9 Storage format for builder-made forms (Phase 5)

Code-defined and builder-defined conversations share one runtime: every question type implements `toDefinition(): array` / `static fromDefinition(array): static`, and a `QuestionRegistry` maps `type` → class (third parties can register their own types).

```json
{
  "schema_version": 1,
  "settings": { "progress_bar": true, "auto_advance": true, "navigation": true },
  "theme": { "accent": "#4f46e5", "font": "Inter", "background_image": null },
  "variables": { "score": 0 },
  "welcome": { "title": "Let's get you set up", "button_label": "Start" },
  "steps": [
    { "key": "name", "type": "short_text", "label": "What's your name?", "required": true,
      "settings": { "max_length": 80 }, "rules": [] },
    { "key": "plan", "type": "multiple_choice", "label": "Hi {name}! Which plan?",
      "settings": { "options": { "starter": "Starter", "enterprise": "Enterprise" } },
      "rules": [
        { "if": { "all": [{ "field": "plan", "op": "is", "value": "enterprise" }] },
          "then": [{ "jump_to": "company_size" }] },
        { "if": "always", "then": [{ "jump_to": "feedback" }] }
      ] }
  ],
  "endings": [{ "key": "default", "type": "end_screen", "title": "Thanks, {name}!" }]
}
```

Tables (Phase 5–6): `conversational_forms` (ulid, slug, title, status, `definition` json, `theme` json, settings, published_at), `conversational_form_versions` (immutable published snapshots, so responses always point at the exact definition they answered), `conversational_form_responses` (form_id, version_id, answers json, variables json, ending, status `partial|completed`, last_step, started_at, completed_at, duration_ms, meta json), `conversational_form_response_events` (step_viewed / step_answered per step, for drop-off). Migrations ship as `.php.stub`, published via the install command, and models are swappable via config.

---

## 3. Public developer API

### 3.1 A conversation inside any Livewire component or Filament page

```php
use Filament\Schemas\Schema;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Questions\{Email, LongText, MultipleChoice, OpinionScale, ShortText, YesNo};
use JaOcero\ConversationalForms\Screens\{EndScreen, WelcomeScreen};

public function form(Schema $schema): Schema
{
    return $schema
        ->components([
            Conversation::make([
                ShortText::make('name')
                    ->label("Hi there 👋 What's your name?")
                    ->required()
                    ->maxLength(80),

                Email::make('email')
                    ->label('Nice to meet you, {name}. Where can we reach you?')
                    ->description("We'll never share it.")
                    ->required(),

                MultipleChoice::make('plan')
                    ->label('Which plan are you interested in?')
                    ->options([
                        'starter' => 'Starter',
                        'team' => 'Team',
                        'enterprise' => 'Enterprise',
                    ])
                    ->required()
                    ->logic([
                        Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'),
                        Logic::always()->jumpTo('recommend'),
                    ]),

                MultipleChoice::make('company_size')
                    ->label('How big is your team?')
                    ->options(['1-10' => '1–10', '11-50' => '11–50', '51+' => '51+']),

                OpinionScale::make('recommend')
                    ->label('How likely are you to recommend us?')
                    ->range(0, 10)
                    ->startLabel('Not likely')
                    ->endLabel('Very likely'),

                YesNo::make('newsletter')->label('Want product updates?'),

                LongText::make('notes')->label('Anything else?')->placeholder('Optional'),
            ])
                ->welcomeScreen(
                    WelcomeScreen::make()
                        ->title('Get started in 60 seconds')
                        ->description('Seven quick questions.')
                        ->buttonLabel('Start'),
                )
                ->endScreens([
                    EndScreen::make('default')->title('Thanks, {name}! 🎉'),
                ])
                ->progressBar()
                ->autoAdvance(),
        ])
        ->statePath('data');
}
```

The Livewire component uses the trait and implements one hook:

```php
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use JaOcero\ConversationalForms\Livewire\Concerns\InteractsWithConversation;
use JaOcero\ConversationalForms\Livewire\ConversationResult;
use Livewire\Component;

class Onboarding extends Component implements HasSchemas
{
    use InteractsWithSchemas;
    use InteractsWithConversation;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function handleConversationSubmission(array $data, ConversationResult $result): void
    {
        Lead::create([...$data, 'ending' => $result->ending]);
    }
}
```

In a Filament `CreateRecord` page: `Conversation::make([...])->submitUsing('create')` and hide the default form actions (a documented recipe plus a `HasConversationFormActions` trait in v0.2).

### 3.2 Fallback fields and `ConversationAware`

```php
use Filament\Forms\Components\{DatePicker, Select};
use JaOcero\RadioDeck\Forms\Components\RadioDeck;

Conversation::make([
    Select::make('country')->options($countries)->searchable()->required(), // Enter advances
    RadioDeck::make('platform')->options(Platform::class),                  // own look, inside our chrome
    DatePicker::make('start_date'),
]);
```

Third-party authors opt in to the richer behaviour:

```php
use JaOcero\ConversationalForms\Contracts\ConversationAware;
use JaOcero\ConversationalForms\Support\KeyboardHint;

class RadioDeck extends Field implements ConversationAware
{
    public function shouldAutoAdvance(): bool
    {
        return ! $this->isMultiple();
    }

    /** @return array<KeyboardHint> */
    public function getKeyboardHints(): array
    {
        return [KeyboardHint::make('A–Z', 'Choose an option')];
    }
}
// …and in its Alpine: $el.dispatchEvent(new CustomEvent('cf-answered', { bubbles: true }))
```

### 3.3 Logic API

```php
use JaOcero\ConversationalForms\Logic\Enums\Operator;
use JaOcero\ConversationalForms\Logic\Logic;

// Jumps
Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size');
Logic::when('plan', Operator::IsNot, 'starter')->and('seats', '>=', 20)->jumpTo('sales_call');
Logic::when(Logic::any([
    Logic::condition('budget', 'between', [5_000, 20_000]),
    Logic::condition('urgent', 'is', true),
]))->jumpTo('priority');
Logic::always()->jumpTo('feedback');

// End early / route to an ending
Logic::when('age', '<', 18)->endWith('not_eligible');

// Calculations and variables
Conversation::make([...])->variables(['score' => 0]);
Logic::when('q1', 'is', 'b')->add('score', 10);
Logic::always()->add('score', fn (Get $get) => (int) $get('bonus'));   // server-only

// Quiz sugar (compiles to the above)
MultipleChoice::make('q1')->options([...])->correct('b')->points(10);

// Show/hide (also within a QuestionGroup, client-side via visibleJs)
ShortText::make('company')->visibleWhen(Logic::condition('employment', 'is', 'employed'));

// Piping in any label/description/ending: {field}, {@variable}
EndScreen::make('passed')->title('You scored {@score}/100, {name}!');
Logic::whenVariable('score', '>=', 70)->endWith('passed');
```

Operators per answer type (`Operator` enum, with `appliesTo(QuestionType)` for builder filtering):

| Type | Operators |
|---|---|
| text | `is`, `is_not`, `contains`, `not_contains`, `starts_with`, `ends_with`, `is_empty`, `is_not_empty` |
| choice (single) | `is`, `is_not`, `is_empty` |
| choice (multiple) | `includes_any`, `includes_all`, `excludes`, `is_empty` |
| number / scale / rating / NPS | `=`, `!=`, `>`, `>=`, `<`, `<=`, `between`, `is_empty` |
| date | `before`, `after`, `on`, `not_on`, `between` |
| yes/no, consent, checkbox | `is` (`true`/`false`) |

### 3.4 Public page (panel-less, v0.1)

```php
namespace App\Livewire\Forms;

use JaOcero\ConversationalForms\Livewire\ConversationPage;

class JobApplication extends ConversationPage
{
    protected static ?string $title = 'Apply: Senior Laravel Developer';

    public function conversation(Conversation $conversation): Conversation
    {
        return $conversation->components([...])->endScreens([...]);
    }

    protected function handleConversationSubmission(array $data, ConversationResult $result): void
    {
        Application::create($data);
    }
}
```

```php
// routes/web.php
Route::livewire('/apply', JobApplication::class)      // Livewire v4 route macro (verified)
    ->middleware('throttle:conversations');
```

Configurable per page: `layout()`, `theme()`, `isFullScreen`, `hiddenFieldsFromQuery`, SEO meta (`title`, `description`, OG image), `allowsEmbedding` (frame-ancestors header, Phase 6).

### 3.5 Panel plugin (Phase 5)

```php
->plugin(
    ConversationalFormsPlugin::make()
        ->publicPath('f')                 // /f/{slug} served with this panel's theme + brand
        ->builder()                       // Forms resource with the visual builder
        ->responses()                     // Responses resource, analytics widgets
        ->navigationGroup('Forms')
)
```

### 3.6 Display columns and entries

```php
AnswerColumn::make('data.plan')->question(MultipleChoice::make('plan')->options($plans)); // shows the label
RatingColumn::make('data.rating')->max(5);
NpsColumn::make('data.recommend');                       // coloured promoter/passive/detractor badge
ResponseEntry::make('answers')->definition($form->definition); // full Q&A transcript in an infolist
```

---

## 4. Phased roadmap

Versioning: `0.x` while the API stabilises (minor = feature phase, patch = fixes), `1.0.0` after the API freeze. Every release: CHANGELOG entry, README updated, `resources/dist` rebuilt, green CI, screenshots refreshed if the UI changed.

### Phase 0: Foundation and spikes (no release)

**Scope**
- Generate from `filamentphp/plugin-skeleton@5.x`. Adapt to radio-deck conventions (`spatie/laravel-package-tools`, `PackageServiceProvider`, `$name`/`$viewNamespace` statics, `getAssets()`, `askToStarRepoOnGitHub('199ocero/conversational-forms')`). Remove the skeleton facade/command/migration we don't need yet.
- `composer.json`: `php ^8.2`, `filament/filament ^5.0`, `illuminate/contracts ^12.0|^13.0`, `spatie/laravel-package-tools ^1.15`. Dev: Pest ^3.7|^4|^5 (+arch, laravel plugins; browser plugin added with the first browser tests), Testbench ^10|^11, Larastan ^3, Rector ^2, Pint.
- `package.json`: esbuild, `@tailwindcss/cli` v4, Vitest, Prettier.
- Wire into the playground: path repository `packages/conversational-forms` (symlink), `jaocero/conversational-forms: *@dev`, plus a playground page per spike.
- CI: tests matrix (skeleton's), phpstan, pint, js (Vitest), dist-is-built check (`npm run build && git diff --exit-code resources/dist`).
- **Spikes** (each answered in a short note in `docs/spikes.md`):
  1. Exposed-method promise semantics on `ValidationException` and morph timing (§2.2, §2.3).
  2. Prebuilt Tailwind v4 CSS without preflight, loaded via `x-load-css` inside a panel and on a panel-less page (§2.8).
  3. Filament's compiled stylesheet + `@filamentScripts` on a panel-less Livewire full page, plus `Route::livewire` (§3.4).
  4. Payload/latency of `advance()` on a 40-question form; partial rendering options.
  5. A fallback `Select`, `DatePicker`, `FileUpload`, `RichEditor` and `RadioDeck` inside hidden steps (Enter conflicts, measurement bugs).

**Definition of done:** the empty package installs into the playground and a fresh Testbench app; CI is green on the full matrix; every spike has a written answer and any plan changes are recorded here.

### Phase 1: v0.1.0 "First conversation" (small but polished)

**Scope**
- `Conversation` layout with auto-wrapping `ConversationStep`, `advance()`, path-based visibility, `InteractsWithConversation`.
- Alpine stage: transitions (slide/fade, direction-aware, reduced-motion aware), the full keyboard spec (§6) for v0.1 fields, focus management, live-region announcements, OK button + "press Enter ↵" hint, up/down navigation arrows, progress bar following the real path.
- `WelcomeScreen`, a single `EndScreen` (plus `Logic` "end" target).
- Questions: `ShortText`, `LongText`, `Email`, `MultipleChoice` (single/multiple, letter keys, "Other", randomize with a **per-session seed** so the order is stable across re-renders, min/max selections, auto-advance on single), `YesNo`, `OpinionScale` (custom range, start/end labels, digit keys with a buffered "10").
- Basic logic: `Logic::when(field, is|is_not, value)->jumpTo()`, `Logic::always()`, forward-only validation in `LogicGraph`, server path recompute on submit.
- Fallback rendering of any Filament field + the Enter-key guard.
- `ConversationPage` (panel-less) with the public layout, dark mode, mobile layout.
- Prebuilt CSS with hook classes and `--cf-*` variables, documented.
- `Testing\TestsConversations` mixin.
- Docs: README (install, quick start, every v0.1 field, keyboard table, theming, testing), banner, light/dark/mobile screenshots, first GIF.

**Definition of done**
- Every public method has PHPDoc with array shapes; PHPStan level 6 clean; Pint/Rector clean.
- Feature tests cover: per-step validation, skipped-required, tampered `advance()` (step not on path), tampered submit (forged answer on an off-path step isn't saved), jumps, the always-fallback, `LogicGraph` errors, every field's state cast, rendering and ARIA attributes.
- Browser tests cover: the full keyboard-only run-through, focus lands on the right control after every transition, Back, auto-advance, mobile viewport (375 px), dark mode, and **zero axe violations** on every v0.1 screen.
- Works inside a Filament panel page and on the panel-less page; verified in the playground in Chrome, Safari and Firefox.
- Tagged `v0.1.0`, submitted to the Filament plugin directory.

### Phase 2: v0.2.0 "More questions"

**Scope:** `Phone` (country selector, E.164 normalisation via optional `propaganistas/laravel-phone` suggest, else regex), `Number` (min/max/step, locale formatting), `Website`, `Dropdown` (searchable combobox, ARIA 1.2 pattern), `Checkbox`, `LegalConsent` (accept/decline, links), `PictureChoice` (image grid, single/multi, letter keys), `Rating` (stars/hearts/custom icon, 1–10), `Nps` (preset of OpinionScale 0–10 with category output), `Statement`, `QuestionGroup`, `HiddenField` (from URL query, allow-list, never rendered). Answer piping `{field}` (server-side; HTML-escaped). `media()` beside or above the question (image/video, layout split on desktop, stacked on mobile). `AnswerColumn`, `AnswerEntry`, `RatingColumn`, `NpsColumn`. Filament page recipe/trait.

**Definition of done:** same bar as v0.1 for every new field (feature + browser + axe); piping XSS tests (`{name}` = `<script>`); README sections and screenshots per field.

### Phase 3: v0.3.0 "Logic engine"

**Scope:** AND/OR condition groups (nested), the full operator matrix (§3.3), show/hide (`visibleWhen` → server visibility + generated `visibleJs`), skip/end early, multiple `EndScreen`s chosen by logic, `Redirect` endings (with piped, URL-encoded query), variables + calculations (`add`, `subtract`, `multiply`, `divide`, `set`), quiz sugar (`correct()`, `points()`), `Score` display in endings, the JS evaluator with shared parity fixtures, client-side progress prediction, `LogicGraph` reachability/unreachable-ending warnings. README grows a dedicated Logic section with a table of contents.

**Definition of done:** ≥ 150 evaluator dataset cases; PHP and JS pass the identical fixture set; a property-style test generates random rule sets and asserts the path always terminates and never visits a step twice; forged `score` in the request payload never changes the result; demo quiz works end to end.

### Phase 4: v0.4.0 "Rich inputs"

**Scope:** `Ranking` (drag *and* keyboard reordering: Space to grab, arrows to move, live-region announcements), `Matrix` (single/multiple per row, a `grid` ARIA pattern on desktop, stacked radiogroups on mobile), `DateQuestion` (typed DD/MM/YYYY segmented boxes with locale order, auto-tab, optional calendar popover, min/max), `TimeQuestion`, `ContactInfo` and `Address` (grouped sub-fields stored as an array, with per-part required flags and `autocomplete` tokens), `FileUpload` (wraps Filament's upload pipeline with our drop-zone UI, so Livewire temporary uploads, disk/visibility config and the validation rules are unchanged), `Signature` (canvas + pointer events, keyboard alternative "type your name", stored as PNG on a configured disk).

**Definition of done:** each field has keyboard-only and screen-reader paths documented and tested; uploads tested with `UploadedFile::fake()`; Signature and Ranking browser-tested on touch emulation.

### Phase 5: v0.5.0 "Builder"

**Scope:** `ConversationalFormsPlugin`; migrations (stubs) + models; `QuestionRegistry`, `toDefinition()/fromDefinition()` for every type; a Forms resource with: list, create, a **builder editor** (Filament `Builder` field with one block per question type, reorderable, with per-block settings), a **visual logic panel** (per-question rule repeater with a field/operator/value picker filtered by `Operator::appliesTo()`), live preview (renders the definition in an iframe of the public page), publish → immutable version, and a **logic map** page (graph from `LogicGraph`, laid out with lazily loaded ELK.js as an `AlpineComponent`, with nodes = steps/endings and edges = jumps/default flow, warnings highlighted). Public route `/f/{slug}` served by `PanelConversationPage` (a `SimplePage` under the panel's theme).

**Definition of done:** a builder-made form and the equivalent code-made form produce identical paths for the shared fixture set (round-trip test `fromDefinition(toDefinition(x)) == x`); builder flows covered by Livewire + browser tests; the docs show a full builder walkthrough.

### Phase 6: v0.6.0 "Responses and sharing" (builder forms)

**Scope:** stored submissions **for builder forms only** (code-defined forms keep returning data to the developer, see §2.7); partial responses (upserted on each `advance()`, keyed by a signed session token); Responses resource (table with `AnswerColumn`s generated from the definition, filters, a transcript view via `ResponseEntry`); CSV/XLSX export via Filament's `Exporter`; analytics widgets (completion rate, average time to complete, per-question drop-off funnel, NPS breakdown); share panel: link, embed snippets (iframe + script with auto-resize via `postMessage`), QR code (an optional `suggest`ed package rendered as SVG); spam protection (honeypot + minimum-time trap built in, `RateLimiter` on `advance`/`submit`, a `CaptchaProvider` contract with a Cloudflare Turnstile driver first); data retention (prune command for partials).

**Definition of done:** analytics numbers verified by fixtures with known answers; export tested; rate-limit and honeypot tests; embed tested in a cross-origin page in a browser test.

### Phase 7: v0.7.0 "Themes"

**Scope:** `Theme` value object (colors, fonts with optional Google Fonts loading, background image/overlay, button/answer styles, corner radius), a theme editor in the builder with live preview, 6 bundled presets, per-form theme stored in JSON, contrast checking (WCAG AA warning when accent/background fail).

**Definition of done:** visual regression screenshots per preset (light/dark/mobile); contrast check unit-tested.

### Phase 8: v0.8.0 "Media answers"

`AudioAnswer` / `VideoAnswer` (MediaRecorder, with a file-upload fallback where unsupported and an explicit permission UX), stored through the same upload pipeline as `FileUpload`.

**Definition of done:** permission-denied and unsupported-browser paths tested; documented threat model (file size, MIME checks, public-page throttling).

### After 1.0: separate add-on packages (decided)

`Payment` and `Scheduler` ship later as **separate packages** so core never depends on Stripe or scheduling SDKs:
- `jaocero/conversational-forms-stripe`: a `Payment` question using the Stripe Payment Element. The amount is computed server-side from variables; the PaymentIntent is created server-side and verified on submit; webhooks are documented. Tested against Stripe test mode in its own CI.
- `jaocero/conversational-forms-scheduler`: a `Scheduler` question with a provider contract (Cal.com / Calendly embed drivers first).

Core's job is to keep the extension points public and documented (`Question`, `ConversationAware`, `QuestionRegistry`, `toDefinition()/fromDefinition()`), so the add-ons need no internal APIs.

### v1.0.0: API freeze

Deprecations removed, README complete, external accessibility review (or at least a recorded NVDA + VoiceOver walkthrough), performance budget met (§5), upgrade guide from 0.x.

---

## 5. Testing strategy

| Layer | Tooling | What |
|---|---|---|
| Unit | Pest 4 | `Evaluator` (dataset per operator × type, incl. nulls/arrays/dates/timezones), `PathResolver`, `LogicGraph`, `Piping` (escaping), `Operator::appliesTo`, definitions round-trip. |
| Feature (Livewire) | Pest + `Livewire\Livewire::test()` + Testbench (no `pest-plugin-livewire`: it can't install with Livewire 4 on PHP 8.2), fixture `TestConversation` component (radio-deck's `TestForm::$components` pattern) | Rendering + ARIA per field, state casts, `advance()` validation, path visibility, submit recompute, tamper cases (calling `advance` with an off-path key, injecting answers for skipped steps, forging variables), `ConversationPage` routing/layout, columns/entries output, `TestsConversations` mixin itself. Filament helpers: `fillForm`, `assertHasFormErrors`, `assertSchemaComponentHidden`, `assertSchemaStateSet`. |
| JS unit | Vitest (jsdom) | keyboard guard, buffered digit typing, focus helpers, the JS evaluator against `tests/fixtures/logic/*.json`. |
| Browser | Pest 4 browser plugin (Playwright) **[verify the plugin's accessibility assertion API]**, run against Testbench's `serve` | Keyboard-only flows, focus after transition, auto-advance timing, transitions with `prefers-reduced-motion`, mobile viewport, dark mode, hidden-step JS fields, axe scans per screen. |
| Architecture | `pest-plugin-arch` | No `dd/dump/ray`; every class in `Questions\` extends `Question` and implements `ConversationAware` and `FormatsAnswer`; `Logic\` has no `Filament\` dependency (except `Get` for server-only closures); strict types where used. |
| Static | Larastan (level 6, raising toward 8), Rector dry-run, Pint `--test` | |

**CI matrix** (`tests.yml`, adapted from skeleton 5.x): OS ubuntu + windows × PHP 8.2/8.3/8.4 (8.5 when available on runners) × Laravel 12/13 (excluding L13 + PHP 8.2), `fail-fast: false` × prefer-lowest/prefer-stable. Separate jobs: `browser-tests.yml` (ubuntu, PHP 8.4, L13, Chromium + WebKit), `js.yml` (Vitest + `npm run build` + a dist-is-up-to-date diff), `phpstan.yml`, `fix-php-code-style.yml`, `update-changelog.yml`. Coverage target: ≥ 90 % on `src/Logic`, ≥ 80 % overall.

**Performance budget** (checked in a benchmark test from Phase 0): `advance()` p95 < 150 ms server time for a 40-question form on CI hardware; conversation JS < 25 kB gzipped; CSS < 20 kB gzipped.

---

## 6. Accessibility and keyboard specification

### 6.1 Global (stage)

| Key | Action | Notes |
|---|---|---|
| `Enter` | OK / next (validates) | Not in `LongText` (see below), not when a listbox/combobox/popup is open, not during IME composition. |
| `Ctrl/⌘ + Enter` | Next from anywhere, incl. `LongText` | |
| `Alt + ↓` / `Alt + ↑` | Next / previous | The on-screen chevrons do the same; plain arrows are left to fields. |
| `Shift + Tab` / `Tab` | Native focus order within the current step | Inactive steps are `inert`. |
| `Esc` | Closes the field popover if open; otherwise nothing | Never discards answers. |
| `Enter` on the Welcome screen | Start | |

### 6.2 Per field

| Field | Keys | Focus on enter | ARIA |
|---|---|---|---|
| ShortText / Email / Phone / Number / Website | native; `Enter` next | the input | `<label for>`, `aria-describedby` (description + error), `aria-invalid`, `aria-required`, `autocomplete`/`inputmode`/`type` per type |
| LongText | `Shift+Enter` newline (default), `Enter` next; or `->submitOnEnter(false)` → `Enter` newline, `⌘/Ctrl+Enter` next | the textarea | as above + character counter in `aria-describedby` (polite) |
| MultipleChoice (single) | `A–Z` select by letter, `↑/↓` move, `Space` select, auto-advance | the selected option or the first | `role="radiogroup"` + `aria-labelledby` the question; each option `role="radio"`, roving tabindex, `aria-keyshortcuts="A"` |
| MultipleChoice (multiple) | `A–Z` toggle, `Space` toggle, `Enter` next | first option | `role="group"`, options `role="checkbox"`, selection count announced |
| "Other" option | letter selects + focuses the inline input; `Enter` confirms | inline input | input labelled "Other, please specify" |
| YesNo | `Y` / `N`, `←/→`, auto-advance | the selected option or "Yes" | radiogroup of two |
| OpinionScale / NPS | digits (buffered 600 ms for `10`), `←/→`, auto-advance | the selected value or the first | radiogroup; each option labelled "7 of 10"; start/end labels via `aria-describedby` |
| Rating | digits, `←/→`, auto-advance | | radiogroup, "3 stars of 5" |
| Dropdown | type-ahead, `↑/↓`, `Enter` choose (does not advance), `Enter` again next | combobox input | ARIA 1.2 combobox + listbox; stage guard respects `aria-expanded` |
| PictureChoice | as MultipleChoice; images have `alt` from the option label | | |
| DateQuestion | digits auto-tab across DD/MM/YYYY, `Backspace` on empty moves back, `Alt+↓` opens the calendar | first segment | `role="group"` with a legend; each segment labelled ("Day", "Month", "Year") |
| Ranking | `Space` grab/drop, `↑/↓` move, `Esc` cancel | first item | list with live-region instructions ("Item 2 of 5, grabbed") |
| Matrix | arrows across rows/columns, `Space` select | first cell | `role="grid"` desktop / per-row radiogroups mobile |
| FileUpload / Signature / Audio / Video | `Enter`/`Space` open the picker; Signature offers a "type instead" alternative | the primary button | status of upload/recording announced |
| Statement / Welcome / End | `Enter` continue | the heading (`tabindex="-1"`) | heading levels: question = `h1` on the public page, `h2` when embedded |

### 6.3 Focus and announcement rules

- After every transition, focus moves to the step's primary control (table above); on a validation error, to the first invalid control. The question number and text are announced via a single polite live region ("Question 3 of 8: Which plan…"). The visible progress bar is `role="progressbar"` with `aria-valuenow` and `aria-valuetext`.
- Visible focus: `--cf-focus-ring` (2 px accent + offset) on every interactive element, never removed; it meets 3:1 contrast against the background in light and dark.
- `prefers-reduced-motion`: no slides or shakes, instant switch, and auto-advance delay 0 → 150 ms highlight only.
- Touch targets ≥ 44×44 px; the layout is single-column below `640px`, the media stacks above the question, and the OK button is sticky at the bottom with safe-area insets; the virtual keyboard doesn't cover the input (`scrollIntoView` on focus with `visualViewport` handling).
- Fallback fields keep Filament's own accessibility markup untouched; the step chrome only adds the number and the live-region text.

---

## 7. Documentation and demo plan

**README is the only documentation (decided)**. There's no docs site; the Filament plugin directory renders it. Keep it navigable with a table of contents at the top, collapsible `<details>` blocks for long option tables, and one screenshot per field. Order: banner → one-line pitch → GIF → requirements → installation (`composer require`, `php artisan filament:assets`, optional install command) → quick start (30 lines) → Conversation options → one section per field (screenshot + options table + keyboard) → logic → public pages → theming (variables table, hook classes list) → display columns/entries → testing helpers → accessibility statement → FAQ/troubleshooting (assets not loading, Enter conflicts) → changelog/contributing/security/credits/license. Same structure and tone as radio-deck's README.


**Demo app**: a separate repo `199ocero/conversational-forms-demo` (a Laravel 13 + Filament 5 panel + public pages), runnable locally with one `composer setup` + seeder. **Hosting is out of scope for this plan (decided): Jay will host it personally later.** Each demo form saves to its own model and migration in the demo app (no plugin storage), showing the intended usage. Forms, landing as features ship:
1. **Onboarding** (v0.1): welcome, name, email, plan with a jump, a scale, yes/no, ending with piping.
2. **NPS survey** (v0.2): `Nps` → logic routes promoters/passives/detractors to different follow-ups; saved to the demo's own `SurveyResponse` model and listed in a panel table with `NpsColumn`.
3. **Quiz with scoring** (v0.3): 8 questions, `correct()`/`points()`, score variable, pass/fail endings, `{@score}` piping.
4. **Job application** (v0.4): `ContactInfo`, `FileUpload` (CV), `Ranking` (priorities), `DateQuestion` (availability), `LegalConsent`, saved to an `Application` model and viewed in a panel resource with `ResponseEntry`.

**60-second video/GIF** (re-recorded at v0.4 and v1.0): 0–10 s welcome → typing name → Enter; 10–25 s letter-key choice auto-advances, the scale by digit; 25–35 s a logic jump visibly skips a question, the progress bar adjusts; 35–45 s dark mode + mobile; 45–55 s the submission appears in the Filament panel table; 55–60 s the install command + repo URL. Recorded at 1280×800, keyboard overlay on, exported as MP4 for the README/social and an optimised GIF ≤ 8 MB.

---

## 8. Risks and open questions

**Filament v5 limitations / unknowns found**
1. **No API to validate one arbitrary field.** It's solved by auto-wrapping children in `ConversationStep` (Wizard's technique). Risk: users calling `getFlatFields()` / relationships across wrappers. Covered by tests in Phase 1.
2. ~~Exposed-method promise on validation failure and morph timing~~ resolved in spike 1: it resolves `null`, after the morph.
3. **Replacing the field wrapper** (for our big question chrome) means re-implementing Filament's accessibility contract (IDs, `getAccessibilityAttributes()`, the `data-field-accessibility` element). If that contract changes in a 5.x minor, our views drift → a dedicated test asserts the attributes for every question.
4. ~~Re-render cost~~ resolved in spike 4: render all steps once, then skip or step-only partial renders (§2.3).
5. **Prebuilt Tailwind v4 CSS**: the build is resolved (spike 2). Still open: confirming `x-load-css` in a browser and checking for a flash of unstyled steps.
6. ~~Panel-less pages need Filament's compiled CSS/JS~~ resolved in spike 3 (`FilamentAsset::getTheme('app')`, `Route::livewire` exists); a visual check is still pending.
7. **Fallback fields' own Enter/arrow handling** (Select, DatePicker, TagsInput, RichEditor) may conflict with the stage; the guard handles known cases, and unknown third-party fields can add `data-cf-keyboard-ignore`. A documented compatibility table is maintained.
8. `JsContent` escaping semantics for client-side piping (v0.3) [verify]. Until then, piping is server-rendered and escaped.
9. Livewire temporary uploads on **unauthenticated** public pages (Phase 4): needs throttling and file-rule documentation; review `livewire.temporary_file_upload` defaults.

**Product / project risks**
- Scope is huge (≈35 field types + builder + analytics). The phases are ordered so each release stands alone; Phase 8 can be dropped without harming 1.0, and Payment/Scheduler are already outside core.
- Name/trademark: "Typeform" is a registered trademark. Descriptive use ("Typeform-style") in the tagline is common, but the package name, logo and visuals must not imitate Typeform's branding or exact UI.
- Logic semantics must stay identical in PHP and JS. That's mitigated by the shared fixture suite as a CI gate.
- Randomized options must be seeded per session, or Livewire re-renders will reshuffle them under the user.

**Decisions log**

| # | Question | Decision |
|---|---|---|
| 1 | Demo hosting | Not part of this plan; Jay hosts it personally later. The demo must run locally out of the box. |
| 2 | Storing responses for code-defined forms | Never. Like a normal Filament form, the conversation returns validated `$data` + `ConversationResult`; the developer saves to their own migrations/models. Plugin tables exist only for the builder (Phase 5–6). |
| 3 | Payment and Scheduler | Separate add-on packages after 1.0 (`-stripe`, `-scheduler`). |
| 4 | Docs | README only, no docs site. |
| 5 | Browser support | Defined below (Jay had no preference; this follows our toolchain's floor). |

| 6 | Laravel 11 | Dropped (2026-10-29). Laravel 11 is out of security support and every 11.x release has unpatched advisories, so Composer refuses to install it. Supported: Laravel 12 and 13. |

**Browser support (decided default):** the last two major versions of Chrome, Edge, Firefox and Safari (macOS and iOS), with a hard floor of **Safari 16.4, Chrome 111 and Firefox 128**. That floor is what Tailwind CSS v4 (used to build our CSS and by Filament v5) itself requires, so supporting anything older isn't possible without dropping Tailwind v4 features. It also covers `inert`, `:has()`, `dvh` units and `visualViewport`, which the stage relies on. Media answers (Phase 8) additionally require MediaRecorder and fall back to a file upload where it's missing. Browser tests run Chromium + WebKit (+ Firefox from v0.4). The README states this in its Requirements section. **[verify]** the exact Tailwind v4 browser floor against its docs when writing the README.

---

## 9. Verification (for this plan and each phase)

- **This deliverable:** `packages/conversational-forms/docs/PLAN.md` exists, renders on GitHub (tables, code fences, diagram) and every `[verify]` item maps to a Phase 0 spike.
- **Per phase:** `composer test` (Pest), `composer analyse`, `composer lint -- --test`, `npm run build && git diff --exit-code resources/dist`, `npx vitest run`, `vendor/bin/pest --group=browser`; then a manual run in the playground (`https://filament-playground.test`) inside a panel page and on the panel-less route, in light/dark, desktop/mobile, keyboard-only and with VoiceOver.
