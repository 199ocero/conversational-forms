<?php

namespace JaOcero\ConversationalForms\Screens;

use Closure;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Support\Piping;

/**
 * An ending that sends the respondent to another page once the conversation
 * is submitted. Answers can be passed along: `{email}` in the URL is replaced
 * by the URL-encoded answer, `{@score}` by a variable.
 *
 *     Redirect::make('book_a_call')->url('https://cal.com/acme?email={email}')
 */
class Redirect extends EndScreen
{
    protected string | Closure | null $url = null;

    public function url(string | Closure | null $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->evaluate($this->url);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function getUrlFor(Conversation $conversation, array $variables): ?string
    {
        $url = $this->getUrl();

        return ($url === null) ? null : Piping::renderUrl($url, $conversation, $variables);
    }
}
