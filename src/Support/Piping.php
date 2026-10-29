<?php

namespace JaOcero\ConversationalForms\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use JaOcero\ConversationalForms\Components\Conversation;

/**
 * Replaces `{field}` tokens in labels and descriptions with the respondent's
 * answers ("Thanks, {name}!").
 *
 * Text is escaped before tokens are replaced, and answers are inserted with
 * Alpine's `x-text`, so an answer is never interpreted as HTML. The server
 * renders the current answer; the browser keeps it in sync as the respondent
 * types, without a request.
 */
final class Piping
{
    public const PATTERN = '/\{([A-Za-z_][A-Za-z0-9_.]*)\}/';

    public static function hasTokens(mixed $text): bool
    {
        return is_string($text) && (preg_match(self::PATTERN, $text) === 1);
    }

    public static function render(string | Htmlable | null $text, ?Conversation $conversation): string | Htmlable | null
    {
        if ((! $conversation instanceof Conversation) || (! self::hasTokens($text))) {
            return $text;
        }

        /** @var string $text */
        $html = preg_replace_callback(
            self::PATTERN,
            fn (array $match): string => self::renderToken($match[1], $conversation),
            e($text),
        );

        return new HtmlString((string) $html);
    }

    private static function renderToken(string $field, Conversation $conversation): string
    {
        $expression = 'pipe(' . Js::from($conversation->getAnswerStatePath($field)) . ', ' . Js::from($conversation->getAnswerLabels($field)) . ')';

        return '<span class="cf-pipe" x-text="' . e($expression) . '">' . e($conversation->formatAnswer($field)) . '</span>';
    }
}
