# Changelog

All notable changes to `conversational-forms` will be documented in this file.

## Unreleased (v0.4.0)

- Questions: `DateQuestion` and `TimeQuestion` (typed boxes, optional calendar), `ContactInfo` and `Address` (several inputs saved as an array), `Matrix`, `Ranking` (drag, touch or keyboard), `FileUpload` (Filament's uploads with a drop zone) and `Signature` (drawn or typed, stored as a PNG).
- Conditional questions (`visibleWhen()`) are always rendered and skip validation and saving while hidden, so they can appear later without a server render.
- Answer shortcuts move focus to the chosen answer, and Enter on an answer in a screen with several questions moves on.
- Fixed: the path cache crashed when answers contained uploaded files.

## Unreleased (v0.3.0)

- Logic operators for text, multiple answers, numbers and dates, with and/or condition groups.
- Variables and calculations (`add()`, `subtract()`, `multiply()`, `divide()`, `set()`), and quiz scoring with `correct()` and `points()`.
- Several end screens chosen with `endWith()`, and `Redirect` endings with answers and variables in the URL.
- `visibleWhen()` on questions, statements and groups, checked on the server and in the browser.
- `{@variable}` piping, and variables in `ConversationResult`.
- Logic rules, variables and correct answers never leave the server.
- A step with several questions no longer auto-advances, and its answer shortcuts apply to the focused question.
- The first field of a step gets focus, in document order.

## Unreleased (v0.2.0)

- Questions: `Website`, `Phone` (normalized numbers), `Number`, `Dropdown` (searchable combobox), `Checkbox`, `LegalConsent`, `PictureChoice`, `Rating` (stars, hearts or thumbs) and `Nps`.
- `Statement` screens and `QuestionGroup` (several questions on one screen).
- `HiddenField`, filled from the URL's query string.
- Answer piping: `{field}` in labels, descriptions and screens, kept in sync in the browser.
- `media()` on every question.
- `AnswerColumn`, `NpsColumn`, `RatingColumn` and `AnswerEntry` for displaying answers.

## Unreleased (v0.1.0)

- `Conversation` layout component: one question per screen, per-step validation, transitions, progress bar, previous / next navigation, auto-advance and full keyboard support.
- Welcome and end screens.
- Questions: `ShortText`, `Email`, `LongText`, `MultipleChoice` (single or multiple, "Other", randomized order, minimum / maximum selections), `YesNo` and `OpinionScale`.
- Logic jumps with `Logic::when()` and `Logic::always()`, validated when the conversation is built and re-evaluated on the server when it is submitted.
- Any Filament field can be used inside a conversation.
- `InteractsWithConversation` for Livewire components and Filament pages, and `ConversationPage` for public, panel-less conversations.
- Prebuilt, themeable stylesheet with `cf-*` hook classes and `--cf-*` CSS variables; dark mode and reduced motion support.
- Testing helpers: `advanceConversation()`, `assertConversationAdvancedTo()`, `assertConversationPath()`, `submitConversation()` and `assertConversationCompleted()`.
