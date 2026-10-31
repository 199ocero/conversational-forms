# Testing

[← Back to the README](../README.md)

## Testing

The plugin adds helpers to Livewire's testing API:

```php
use Livewire\Livewire;

Livewire::test(Onboarding::class)
    ->set('data.name', 'Jay')
    ->advanceConversation('name')
    ->assertConversationAdvancedTo('email')
    ->set('data.email', 'jay@example.com')
    ->set('data.plan', 'starter')
    ->assertConversationPath(['name', 'email', 'plan', 'recommend'])
    ->submitConversation()
    ->assertConversationCompleted();
```

| Method | Description |
| --- | --- |
| `advanceConversation($step)` | Validate a question and move past it, like pressing Enter. |
| `assertConversationAdvancedTo($step)` | Assert where the last `advanceConversation()` went (`null` after the last question). |
| `assertConversationPath($steps)` | Assert the questions the respondent would go through, given the current answers. |
| `submitConversation()` | Submit the conversation. |
| `assertConversationCompleted($ending = null)` | Assert it was submitted without errors. |
