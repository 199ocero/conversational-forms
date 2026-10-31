# Theming

[← Back to the README](../README.md)

## Theming

### Themes

A theme sets a conversation's colours, font, corner radius and background image, in light and dark mode alike; the public page switches to light or dark to match its background. Six themes are bundled (`paper`, `midnight`, `forest`, `sunset`, `mono` and `lavender`), and any of them can be adjusted:

```php
use JaOcero\ConversationalForms\Themes\Theme;

Conversation::make([...])->theme(Theme::preset('midnight'));

Conversation::make([...])->theme(
    Theme::make()
        ->accent('#e11d48')      // buttons, chosen answers and focus rings
        ->background('#fff7f1')
        ->text('#3b1f1a')        // questions
        ->answer('#9a3412')      // typed answers (defaults to the text colour)
        ->font('DM Sans')        // see Themes\Fonts::FAMILIES
        ->radius('large')        // none, small, medium, large or full
        ->backgroundImage('https://example.com/sky.jpg')
        ->overlay(60),           // how much the background colour covers the image
);
```

In the builder, **Design** has the same options and the preview follows them as they change. It warns when colours fall short of WCAG AA contrast (4.5:1 for text, 3:1 for buttons), and every bundled theme passes. A theme goes live with the next published version.

Fonts are loaded from [Bunny Fonts](https://fonts.bunny.net), a privacy-friendly host of the Google Fonts families. Set `conversational-forms.fonts.url` to `null` to never load fonts from another site; themes then use the system font.

### CSS variables

Conversations follow your Filament panel's colors and dark mode automatically. To change them, set any of these CSS variables on `.cf-conversation`, for example in your theme or with `extraAttributes()`:

| Variable | Default | Used for |
| --- | --- | --- |
| `--cf-accent` | `--primary-600` (`--primary-400` in dark mode) | Buttons, answers, focus rings |
| `--cf-accent-contrast` | white, or the primary palette's darkest shade when white is too light on it | Text on the accent color |
| `--cf-fg` / `--cf-muted` | `--gray-950` / `--gray-500` | Text |
| `--cf-bg` | transparent | Background |
| `--cf-answer-color` | `--cf-fg` | Text typed into answers (set it to `var(--cf-accent)` for accent-coloured answers) |
| `--cf-surface` | white (`--gray-900` in dark mode) | Background of the "Other" text field |
| `--cf-danger` | `--danger-600` | Validation messages |
| `--cf-font-family` | Filament's font | Font |
| `--cf-question-size` / `--cf-answer-size` | `1.875rem` / `1.5rem` on larger screens | Question and answer text size |
| `--cf-radius` | `0.5rem` | Corner radius |
| `--cf-content-width` | `42rem` | Width of full-screen conversations |
| `--cf-transition-duration` | `450ms` (0 with reduced motion) | Transitions |

```php
Conversation::make([...])->extraAttributes(['style' => '--cf-accent: #0f766e; --cf-radius: 1rem']);
```

Every element has a stable class you can target: `cf-conversation`, `cf-progress`, `cf-step`, `cf-question`, `cf-question-number`, `cf-question-label`, `cf-question-description`, `cf-question-error`, `cf-input`, `cf-textarea`, `cf-choices`, `cf-choice`, `cf-choice-key`, `cf-scale`, `cf-scale-option`, `cf-button`, `cf-hint`, `cf-navigation`, `cf-screen`, `cf-welcome-screen` and `cf-end-screen`. The active step has `cf-active` and a selected answer has `cf-selected`.
