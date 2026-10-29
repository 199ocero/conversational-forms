# Phase 0 spikes

Answers to the **[verify]** items in [PLAN.md](PLAN.md), measured against Filament **v5.11.0**, Livewire **v4.4.8**, Laravel 13.39, PHP 8.4, Tailwind CSS 4.3.3 (2026-10-29).

The throwaway prototype lives in the playground app, not in this package:

| File | Purpose |
|---|---|
| `app/Spikes/ConversationalForms/SpikeConversation.php` | Layout component: wraps each field in a step, `#[Exposed] advance()`, path resolver with one jump |
| `app/Spikes/ConversationalForms/SpikeStep.php` | Wizard-style step with its own child schema |
| `app/Spikes/ConversationalForms/SpikeQuestions.php` | Text, Radio, Select (searchable), DatePicker (JS), RichEditor, FileUpload, RadioDeck, numeric fillers |
| `app/Filament/Pages/ConversationalFormsSpike.php` | The conversation inside a panel page (`/admin/conversational-forms-spike?filler=32`) |
| `app/Livewire/ConversationalFormsPublicSpike.php` + `resources/views/spikes/conversational-forms/*` | The conversation on a panel-less public page (`/spikes/conversation`) |
| `tests/Feature/ConversationalFormsSpikeTest.php` | Server-side evidence (6 tests) |

Delete all of it once Phase 1 lands.

Status: **answered** = verified by source + test; **answered (source)** = verified by reading the source, to be confirmed by a browser test in Phase 1; **open** = needs a browser.

---

## Spike 1: exposed-method results and morph timing (answered)

**What `await this.$advance()` resolves to when validation fails.**
`$advance()` is a magic created by Filament's `filamentSchemaComponent` scope (`vendor/filament/schemas/resources/js/components/component.js`). It calls `Livewire.fireAction($wire, 'callSchemaComponentMethod', [key, method, args])`.

- A `ValidationException` thrown inside the exposed method is handled by Livewire's normal call pipeline: the errors go to the error bag, the component renders, and the action has no return value. So **the promise resolves to `null`** and doesn't reject. Test: `test_a_failed_validation_records_errors_and_returns_nothing` (`assertHasErrors(['data.name' => 'required'])->assertReturned(null)`).
- Rejection (`{status: 422, errors}`) only happens for methods marked with Livewire's `#[Json]` attribute (`Livewire\Features\SupportJson`). We don't use it.
- **Decision:** `advance()` returns an array on success and `null` on validation failure or a stale/tampered step. The JS treats `null` as "stay and focus the first invalid control". Network or server errors reject, and the JS wraps the call in `try/finally` so the stage never gets stuck in `isAdvancing`.

**Does the promise resolve before or after the DOM morph?**
After. In `livewire.esm.js` the response handler runs `Alpine.transaction(async () => { mergeNewSnapshot(); processEffects(); await invokeOnMorph(); invokeOnMorphed() }).finally(() => resolveActionPromises(...))`. So when `await this.$advance()` returns, any newly rendered step is already in the DOM. **Decision:** no `morphed` hook is needed.

**Other confirmations**
- A step key that isn't on the resolved path returns `null` (`test_a_tampered_step_that_is_not_on_the_path_is_rejected`).
- Wrapping each field in a step with its own child schema and calling `$step->getChildSchema()->validate()` validates **only that step** (`test_advancing_validates_only_the_current_step_and_returns_the_next_one`).
- Injecting `->hidden(fn () => ! onPath)` into steps means `form->getState()` neither validates nor returns a skipped required question (`test_a_skipped_required_step_is_not_validated_on_submit`).

## Spike 2: prebuilt Tailwind v4 CSS (build answered; loading open)

**Build: answered.** `resources/css/index.css` compiles with the Tailwind CLI to **only our rules**:

```css
@import 'tailwindcss/theme.css' layer(theme) theme(inline);   /* theme values inlined, no --color-* vars emitted */
@source not inline('*');                                    /* never scan for utility classes */
@custom-variant dark (&:where(.dark, .dark *));             /* Filament's dark-mode convention */
```

- No preflight and no utilities are emitted, only `@layer components { .cf-… }` plus the `@property` declarations that `@apply ring-*` needs (Filament's own CSS has the same).
- `@apply` works. Fallback colours must use `--theme(--color-indigo-600)`, not `var(--color-indigo-600)`, because inline mode doesn't define the variables. `--theme(--radius-full)` doesn't exist in v4 (use `9999px`).
- `--minify` flattens CSS nesting (Lightning CSS), so the output doesn't need native nesting support.
- Current output: **2.2 kB** CSS, **2.5 kB** JS.

**Loading (open, needs a browser):** confirm `x-load-css` applies the stylesheet inside a panel and on the panel-less page, and check for a flash of unstyled steps before it arrives. If there is one, Phase 1 adds `x-cloak` on the stage until the component initialises, or registers the CSS without `loadedOnRequest()`.

## Spike 3: panel-less public page (server answered; visual check open)

- `Route::livewire()` **exists in Livewire v4** (`Livewire\Mechanisms\HandleRouting`). `Route::livewire('/spikes/conversation', ConversationalFormsPublicSpike::class)` works without login.
- Filament's compiled stylesheet is registered as a **Theme** asset with id `app` (`FilamentServiceProvider`: `Theme::make('app', __DIR__ . '/../dist/theme.css')`). Outside a panel, `FilamentAsset::getTheme('app')?->getHtml()` renders the `<link>` to `css/filament/filament/app.css`. Don't use `filament()->getTheme()` because it needs a current panel.
- A minimal layout that works: `@filamentStyles` + the `app` theme link + `@filamentScripts(withCore: true)` (Livewire's own assets are injected automatically). `@filamentStyles` also emits the full `--primary-*` / `--gray-*` / `--danger-*` variables and the Inter font, so our `--cf-*` tokens resolve to the Filament palette with no panel.
- Test: `test_the_public_page_renders_without_a_panel_or_login` (200, theme link, our JS and CSS, all steps).
- **Open:** a visual check in a browser, in light and dark.

## Spike 4: `advance()` cost on a 41-question form (answered)

In-process measurement (`Livewire::test`, 15 calls, p50; local Apple Silicon, PHP 8.4, no HTTP):

| Steps | Re-render after `advance()` | Time | Response effects |
|---|---|---|---|
| 9 | full component render | 6.1 ms | 116 kB HTML |
| 41 | full component render | 22.2 ms | 232 kB |
| 41 | `$step->partiallyRender()` (only the current step) | 6.5 ms | 4.6 kB |
| 41 | `PartialsComponentHook::skipPartialRender()` (no render) | 6.2 ms | 0.6 kB |

The method body itself takes under 0.4 ms (path resolution and step validation). Almost all of the cost is re-rendering the whole Livewire component, and the HTML is dominated by heavy fallback fields (RichEditor, FileUpload, Select).

**Decision (changes PLAN.md §2.3):**
- Render **every** step in the DOM, including off-path ones (inactive, `hidden` + `inert`). Filament's `hidden()` on off-path steps then only drives validation and dehydration, not rendering. A path change therefore never needs new DOM, so a successful `advance()` can **skip rendering entirely** (0.6 kB).
- On validation failure, call `$step->partiallyRender()` before validating, so only the failing step re-renders with its errors (4.6 kB).
- Steps need a `key()` to be partially rendered (`LogicException` otherwise). `ConversationStep` gets `cf-step-{name}` keys.
- **Phase 1 must verify** that a hidden component can be rendered with `toHtml()` while its fields stay interactive, and that error messages appear after a partial render.

## Spike 5: fallback fields inside inactive steps (open, needs a browser)

Server side, every fallback field renders inside a step (Select, DatePicker with `native(false)`, RichEditor, FileUpload, RadioDeck). Each uses `x-load` and loads its Alpine component asynchronously. What still needs a real browser:

1. Enter inside the searchable Select's search input and inside the DatePicker's popover must not advance (the guard checks `aria-expanded="true"`; covered by Vitest, not yet confirmed against Filament's actual markup).
2. Enter inside RichEditor must insert a newline (the guard ignores `contenteditable`).
3. RichEditor and FileUpload (FilePond) initialised while their step was `visibility: hidden`: check that they lay out correctly when shown (the stage dispatches `cf-step-shown` and a synthetic `resize`).
4. RadioDeck keyboard navigation inside a step.

## Toolchain findings (answered)

- **Pest:** `pestphp/pest-plugin-livewire` only supports Livewire 4 from v4.1 (PHP 8.3+) and v5 (PHP 8.4+), so with Filament 5 it can't install on PHP 8.2. The package therefore **doesn't use the plugin**. Tests call `Livewire\Livewire::test()` directly, which keeps PHP 8.2 in the CI matrix. Constraints are `pestphp/pest ^3.7|^4.0|^5.0` with matching plugins (Pest 5 / PHPUnit 13 resolve on PHP 8.4).
- **PHPUnit config:** the skeleton's `<coverage><report>` block makes Pest try to collect coverage on every run and stop with "No code coverage driver available" when none is installed. It's removed; `composer test-coverage` still works when a driver exists. (radio-deck has the same block and the same local failure.)
- **Larastan** needs `includes: vendor/larastan/larastan/extension.neon` because `phpstan/extension-installer` isn't installed.
- **Rector:** the `strictBooleans` prepared set no longer exists, and `SafeDeclareStrictTypesRector` is skipped (Filament and the author's plugins don't use strict types).
