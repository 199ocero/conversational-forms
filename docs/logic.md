# Logic

[← Back to the README](../README.md)

## Logic

Logic decides what happens after a question is answered: which question comes next, whether the conversation ends early and with which end screen, and how variables such as a score change.

```php
use JaOcero\ConversationalForms\Logic\Logic;

MultipleChoice::make('plan')
    ->options([...])
    ->logic([
        Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'),
        Logic::when('plan', 'is', 'none')->end(),                 // finish now
        Logic::when('plan', 'is', 'student')->endWith('student'), // finish with a specific end screen
        Logic::always()->jumpTo('recommend'),                    // fallback
    ]);
```

Rules are evaluated **in order**. Every matching rule runs its calculations, and the first matching rule that jumps or ends decides where to go; the rules after it are skipped. Without such a rule, the conversation moves to the next question. Questions (and statements and groups) are referred to by their name.

### Conditions

```php
// All conditions must match (AND):
Logic::when('plan', 'is', 'team')->and('seats', '>=', 5)->jumpTo('team_size');

// At least one must match (OR):
Logic::when('plan', 'is', 'enterprise')->or('seats', '>', 100)->jumpTo('sales');

// Nested groups:
Logic::when(Logic::all([
    Logic::condition('country', 'is', 'ph'),
    Logic::any([
        Logic::condition('age', '<', 18),
        Logic::condition('student', 'is', true),
    ]),
]))->jumpTo('discount');
```

A single chain can't mix `and()` and `or()`; use `Logic::all()` and `Logic::any()` to group conditions instead.

| Answer | Operators |
| --- | --- |
| Any | `is` (`=`), `is_not` (`!=`), `is_empty`, `is_not_empty` |
| Text | `contains`, `not_contains`, `starts_with`, `ends_with` (case-insensitive) |
| Multiple answers | `includes_any`, `includes_all`, `excludes` (value: an array); `is` matches any chosen answer |
| Numbers, scales, ratings | `gt` (`>`), `gte` (`>=`), `lt` (`<`), `lte` (`<=`), `between` (value: `[min, max]`, inclusive) |
| Dates (`YYYY-MM-DD`) | `before`, `after`, `on`, `not_on`, `between` |

Numbers compare by value (`'7.50'` is `7.5`), `false` and `0` are answers rather than "empty", and an empty string counts as unanswered.

### Variables and scores

Variables are calculated by logic, for example a quiz score or a lead score. They start at 0 unless you give them a starting value:

```php
Conversation::make([...])
    ->variables(['score' => 0, 'lives' => 3]);

Logic::when('q1', 'is', 'b')->add('score', 10);
Logic::always()->subtract('lives', 1);   // also multiply(), divide() and set()

Logic::whenVariable('score', '>=', 70)->endWith('passed');
```

For quizzes, mark the correct answer and the points it is worth. For a multiple-answer question, every correct answer and no other must be chosen:

```php
MultipleChoice::make('capital')->options([...])->correct('manila')->points(10);
MultipleChoice::make('primes')->options([...])->multiple()->correct(['2', '3'])->points(10);
```

Show a variable with `{@score}` in an end screen, and read it when the conversation is submitted:

```php
EndScreen::make('passed')->title('You scored {@score} points, {first_name}!');

protected function handleConversationSubmission(array $data, ConversationResult $result): void
{
    $result->variable('score'); // also $result->variables and $result->ending
}
```

Variables, logic rules and correct answers never leave the server: they are not in the page and not in any response, so a respondent can't read the answers to a quiz or change their score. That is also why `{@variable}` is meant for end screens: it is filled in when the conversation is submitted.

### Endings

Give a conversation several end screens and choose one with logic. The first is shown when no rule chooses another:

```php
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\Redirect;

Conversation::make([...])->endScreens([
    EndScreen::make('default')->title('Thanks!'),
    EndScreen::make('not_eligible')->title('Sorry, you need to be 18 or older.'),
    Redirect::make('book_a_call')->url('https://cal.example.com/acme?email={email}'),
]);

Number::make('age')->logic([Logic::when('age', '<', 18)->endWith('not_eligible')]);
```

A `Redirect` ending sends the respondent to a URL after the conversation is submitted. `{field}` and `{@variable}` tokens in the URL are replaced with URL-encoded values.

### Showing and hiding questions

`visibleWhen()` shows a question, statement or group only when its conditions match:

```php
QuestionGroup::make('work', [
    MultipleChoice::make('employment')->options(['employed' => 'Employed', 'student' => 'Student']),
    ShortText::make('company')
        ->required()
        ->visibleWhen(Logic::condition('employment', 'is', 'employed')),
]);

Statement::make('team_tips')->visibleWhen(Logic::any([...]));
```

The question appears or disappears as soon as the earlier answer changes, without a request to the server. A hidden question is never required and its answer is not saved. A whole step (a single question, statement or group) whose conditions don't match is skipped. Visibility can only depend on answers, not on variables.

### Safety

- Jumps can only move forward, so a conversation can never loop. A rule that jumps backwards, or to a question or end screen that doesn't exist, throws an `InvalidLogicException` with a clear message as soon as the conversation is rendered.
- Questions that logic skips are not required, and their answers are not saved.
- The path, the ending and every variable are worked out again on the server when the conversation is submitted. A modified browser can't skip a required question, submit answers to questions that weren't on its path, choose its ending or change a score.
- Answers to questions that are no longer on the path (for example after the respondent goes back and changes an earlier answer) never influence later rules.

### Logic for other fields

For fields that aren't conversation questions (see [Using other Filament fields](questions.md#using-other-filament-fields)), pass logic to the conversation, keyed by the field name:

```php
Conversation::make([
    Select::make('country')->options([...]),
    ShortText::make('state'),
    Email::make('email'),
])->logic([
    'country' => [Logic::when('country', 'is', 'ph')->jumpTo('email')],
]);
```

## Saving answers

The plugin never stores anything. Answers go through Filament's normal validation and dehydration, and `InteractsWithConversation` hands them to your component once the conversation is complete:

```php
use JaOcero\ConversationalForms\Livewire\ConversationResult;

protected function handleConversationSubmission(array $data, ConversationResult $result): void
{
    // $data: the validated answers, without skipped questions.
    // $result->path: the questions the respondent went through, in order.
    // $result->ending: the end screen that was shown.

    Application::create($data);
}
```

If your schema isn't called `form`, override `getConversationSchemaName()`. To use your own method instead (for example `create` on a Filament create page), call `->submitUsing('create')` on the conversation.
