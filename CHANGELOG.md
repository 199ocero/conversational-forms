# Changelog

All notable changes to `conversational-forms` will be documented in this file.

## v0.9.0 - 2026-10-31

The first tagged release. Everything below was built in phases that were never tagged on their own, so it all ships in this release.

### Documentation

- A shorter README to get started, with the full reference moved to `docs/` (questions, logic, public pages, the builder, theming, testing, keyboard and accessibility).
- A header image in `art/`.

### Live editor

- The builder is now a full-screen editor: an outline of the conversation (add, duplicate, delete and drag steps, including questions in groups), a live preview drawn in the browser as you type, and the selected item's settings. Edits stay in the browser until saved (with ⌘ / Ctrl + S and a warning before leaving with unsaved changes); a failed save selects the step that needs attention. The Design and Settings tabs are part of the editor, and the theme preview is the live preview itself, with contrast warnings.
- Text is edited in place in the preview (questions, descriptions, choices, buttons, welcome and end screens), and choices are added and removed there too. A toolbar above the preview holds Required, Image, Logic and Settings; the rest of a step's options open in a panel on demand. Logic has its own view, listing every step's conditions and rules as sentences.
- The draft saves itself a few seconds after the last change (`builder.autosave_seconds`, `null` to turn it off). Problems found while saving by itself are only marked in the editor.
- Preview is now **Test run**: the draft's public page, with logic.
- A new step's key follows its text until it's saved, and a duplicated step gets keys that aren't taken (`email_2`).
- `QuestionType::previewView()` draws other packages' question types in the live preview.
- Browser tests (Chromium, through the Pest browser plugin) answer a conversation with the keyboard alone, check focus, logic jumps, going back, the phone layout and dark mode, and scan every screen for accessibility issues with axe.
- Fixed: with a light primary colour such as Filament's default amber, buttons and selected answers had white text below WCAG AA contrast; they now use the palette's darkest shade.
- Fixed: answers and the previous / next buttons are at least 44 × 44 px on touch screens.
- Fixed: a `style` passed with `extraAttributes()` was ignored on themed conversations; it's now kept (and wins over the theme's variables).

### Media answers (Phase 8)

- Questions: `AudioAnswer` and `VideoAnswer`, recorded in the browser with MediaRecorder (a timer, a player to listen back, record again) and uploaded like `FileUpload`, with a duration limit, server-checked size and type limits, and a file upload when recording isn't possible (permission denied, no device, a busy device, an unsupported browser).
- Both are available in the builder.

### Themes (Phase 7)

- Themes: `Theme` sets a conversation's colours, font, corner radius and background image (with an overlay), and the public page follows its light or dark background. Six bundled themes (`Theme::preset()`), all passing WCAG AA contrast.
- A Design tab in the builder with presets, colour pickers, fonts, corners, a background image, a live preview and contrast warnings. Themes are published with each version.
- Fonts load from Bunny Fonts, or not at all (`fonts.url` set to `null`).

### Responses and sharing (Phase 6)

- Responses for builder-made conversations: saved as partial after each step and completed on submit, with the version that was answered. Previews are never stored; unfinished responses are pruned after 30 days (`model:prune`).
- A Responses tab per conversation: started / completed / completion rate / average time, a drop-off chart, NPS, a table with one readable column per question, filters, a transcript of each response, and CSV / Excel export.
- Sharing: the link, an iframe snippet, an embed script whose iframe grows with the conversation (`/f/embed.js`), and a QR code when `bacon/bacon-qr-code` is installed.
- Spam protection on public pages: a honeypot field, a minimum time, a per-visitor request limit, and an optional captcha (`CaptchaProvider`, with Cloudflare Turnstile built in).
- The test suite also runs against MySQL in CI; migrations name their keys explicitly to stay under MySQL's 64-character limit.

### Builder (Phase 5)

- The builder: `ConversationalFormsPlugin` adds a Conversations resource to a panel, with a block per question type, settings, images, quiz scoring, "show only when" conditions and logic rules edited as rows, a welcome screen, endings, variables and hidden fields.
- Drafts and published versions: the public page shows the latest published version while the draft is edited; preview, publish, close and reopen.
- A logic map of each conversation, with warnings for steps and endings that can never be reached.
- Public pages for builder-made conversations at `/f/{slug}` (configurable), and a `ConversationalFormSubmitted` event.
- Definitions: `ConversationDefinition` turns conversations into JSON and back; `QuestionTypeRegistry` and `QuestionType` let other packages add question types; `Rule::fromArray()`, `Operator::appliesTo()` and `AnswerType`.
- Config file and migration (`php artisan conversational-forms:install`).

### Rich inputs (Phase 4)

- Questions: `DateQuestion` and `TimeQuestion` (typed boxes, each with a list to pick from, plus a calendar for dates and an AM / PM box for 12-hour locales), `ContactInfo` and `Address` (several inputs saved as an array), `Matrix`, `Ranking` (drag, touch or keyboard), `FileUpload` (Filament's uploads with a drop zone) and `Signature` (drawn or typed, stored as a PNG).
- Conditional questions (`visibleWhen()`) are always rendered and skip validation and saving while hidden, so they can appear later without a server render.
- Answer shortcuts move focus to the chosen answer, and Enter on an answer in a screen with several questions moves on.
- Fixed: the path cache crashed when answers contained uploaded files.

### Logic engine (Phase 3)

- Logic operators for text, multiple answers, numbers and dates, with and/or condition groups.
- Variables and calculations (`add()`, `subtract()`, `multiply()`, `divide()`, `set()`), and quiz scoring with `correct()` and `points()`.
- Several end screens chosen with `endWith()`, and `Redirect` endings with answers and variables in the URL.
- `visibleWhen()` on questions, statements and groups, checked on the server and in the browser.
- `{@variable}` piping, and variables in `ConversationResult`.
- Logic rules, variables and correct answers never leave the server.
- A step with several questions no longer auto-advances, and its answer shortcuts apply to the focused question.
- The first field of a step gets focus, in document order.

### More questions (Phase 2)

- Questions: `Website`, `Phone` (normalized numbers), `Number`, `Dropdown` (searchable combobox), `Checkbox`, `LegalConsent`, `PictureChoice`, `Rating` (stars, hearts or thumbs) and `Nps`.
- `Statement` screens and `QuestionGroup` (several questions on one screen).
- `HiddenField`, filled from the URL's query string.
- Answer piping: `{field}` in labels, descriptions and screens, kept in sync in the browser.
- `media()` on every question.
- `AnswerColumn`, `NpsColumn`, `RatingColumn` and `AnswerEntry` for displaying answers.

### First conversation (Phase 1)

- `Conversation` layout component: one question per screen, per-step validation, transitions, progress bar, previous / next navigation, auto-advance and full keyboard support.
- Welcome and end screens.
- Questions: `ShortText`, `Email`, `LongText`, `MultipleChoice` (single or multiple, "Other", randomized order, minimum / maximum selections), `YesNo` and `OpinionScale`.
- Logic jumps with `Logic::when()` and `Logic::always()`, validated when the conversation is built and re-evaluated on the server when it is submitted.
- Any Filament field can be used inside a conversation.
- `InteractsWithConversation` for Livewire components and Filament pages, and `ConversationPage` for public, panel-less conversations.
- Prebuilt, themeable stylesheet with `cf-*` hook classes and `--cf-*` CSS variables; dark mode and reduced motion support.
- Testing helpers: `advanceConversation()`, `assertConversationAdvancedTo()`, `assertConversationPath()`, `submitConversation()` and `assertConversationCompleted()`.
