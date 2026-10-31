# Keyboard, accessibility and troubleshooting

[← Back to the README](../README.md)

## Keyboard shortcuts

| Keys | Action |
| --- | --- |
| Enter | Continue (validates the current question) |
| Ctrl / Cmd + Enter | Continue from anywhere, including a long text answer |
| Shift + Enter | Line break in a long text answer |
| Alt + ↓ / Alt + ↑ | Next / previous question |
| A–Z | Pick a multiple choice or picture choice answer |
| Y / N | Answer a yes / no question |
| 0–9 | Pick a value on an opinion scale, NPS or rating (type `1` `0` for 10 on a 0–10 scale) |
| Space | Tick a checkbox |
| ↑ ↓, Enter, Esc | Move through, choose from, or close a dropdown's list |
| Space, then ↑ ↓, then Space | Pick up, move and drop a ranking item (Esc cancels) |
| digits | Fill in date and time boxes; focus moves on as each box fills up |
| ↑ ↓ ← → | Move between answers |
| Space | Toggle the focused answer |
| Tab / Shift + Tab | Move between controls on the current question |

## Accessibility

- Every question is properly labelled, and descriptions and errors are linked with `aria-describedby`. Choices use radio group and checkbox semantics with roving focus, and shortcuts are announced with `aria-keyshortcuts`.
- Inactive questions are `inert`, so they're skipped by keyboard and screen readers. Focus moves to the new question after every transition, and a live region announces the progress ("Question 3 of 8").
- Validation errors move focus to the invalid answer and are announced.
- Focus is always visible, and transitions are turned off for people who prefer reduced motion.

## Troubleshooting

**The conversation is unstyled or doesn't react.** Run `php artisan filament:assets` and hard-refresh the page.

**Enter does something unexpected inside a third-party field.** Add `data-cf-keyboard-ignore` to the field's element via `extraAttributes()`. Ctrl / Cmd + Enter always continues.

**A field that depends on another answer doesn't update while typing.** Questions skip the server render after each change, because every question is rendered once. If you use `->live()` with `afterStateUpdated()` to change other parts of the form, call `->skipRenderAfterStateUpdated(false)` on that question.

## Browser support

The last two versions of Chrome, Edge, Firefox and Safari (Safari 16.4+, Chrome 111+, Firefox 128+).
