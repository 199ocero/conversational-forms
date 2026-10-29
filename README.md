# Conversational Forms for Filament

Typeform-style conversational forms for Filament. One question at a time, fully in your Laravel app.

> [!WARNING]
> This package is under active development and has not been released yet. The API will change until `v1.0.0`.
> Follow the roadmap in [docs/PLAN.md](docs/PLAN.md).

## What it will do

- A `Conversation` layout component that shows one question per screen, with smooth transitions, a progress bar, and welcome and end screens.
- Purpose-built question fields (short text, multiple choice, yes/no, opinion scale, and many more) with full keyboard support and auto-advance.
- Any other Filament field, built-in or third-party, still works inside a conversation.
- Logic jumps, multiple endings, scoring and answer piping, evaluated on the server so they cannot be faked.
- Public, shareable conversation pages that work with or without a Filament panel.
- Accessible, dark-mode aware and mobile friendly, with no theme setup required.

Answers flow through Filament's normal form pipeline (validation, state, `$get()` / `$set()`, `->visible()`), and the validated data is handed back to you to save however you like.

## Requirements

- PHP 8.2+
- Laravel 11.28+
- Filament 5

## Credits

- [Jay-Are Ocero](https://github.com/199ocero)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
