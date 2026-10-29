# Changelog

All notable changes to `conversational-forms` will be documented in this file.

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
