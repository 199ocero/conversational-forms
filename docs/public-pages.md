# Public pages

[← Back to the README](../README.md)

## Public pages

`ConversationPage` serves a full-screen conversation at a public URL. It doesn't need a Filament panel or a logged-in user.

```php
namespace App\Livewire;

use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Livewire\ConversationPage;
use JaOcero\ConversationalForms\Livewire\ConversationResult;

class JobApplication extends ConversationPage
{
    protected static ?string $title = 'Apply: Senior Laravel Developer';

    public function conversation(Conversation $conversation): Conversation
    {
        return $conversation->components([
            // ...
        ]);
    }

    protected function handleConversationSubmission(array $data, ConversationResult $result): void
    {
        Applicant::create($data);
    }
}
```

```php
// routes/web.php
use App\Livewire\JobApplication;

Route::livewire('/apply', JobApplication::class)->middleware('throttle:60,1');
```

The page uses a minimal layout with Filament's styles, follows the visitor's light or dark mode, and works without a Vite build. To use your own layout, set `protected static string $layout = 'layouts.app';` on the page; your layout must include `@filamentStyles` and `@filamentScripts`.
