<?php

namespace JaOcero\ConversationalForms\Captcha;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use JaOcero\ConversationalForms\Contracts\CaptchaProvider;
use Throwable;

/**
 * Cloudflare Turnstile. Set `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` and
 * `CONVERSATIONAL_FORMS_CAPTCHA=turnstile`.
 */
class Turnstile implements CaptchaProvider
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        protected ?string $siteKey = null,
        protected ?string $secretKey = null,
    ) {
        $this->siteKey ??= config('conversational-forms.captcha.turnstile.site_key');
        $this->secretKey ??= config('conversational-forms.captcha.turnstile.secret_key');
    }

    public function render(): Htmlable
    {
        $siteKey = e((string) $this->siteKey);

        return new HtmlString(<<<HTML
            <div class="cf-captcha" wire:ignore>
                <div
                    class="cf-turnstile"
                    data-sitekey="{$siteKey}"
                    data-appearance="interaction-only"
                    data-callback="cfTurnstileCallback"
                ></div>
            </div>
            <script>
                window.cfTurnstileCallback = (token) => window.Livewire?.first()?.\$wire?.set('captchaToken', token, false)
            </script>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=cfTurnstileLoaded&render=explicit" async defer></script>
            <script>
                window.cfTurnstileLoaded = () => document.querySelectorAll('.cf-turnstile').forEach((element) => window.turnstile.render(element))
            </script>
            HTML);
    }

    public function verify(?string $token, ?string $ip): bool
    {
        if (blank($token) || blank($this->secretKey)) {
            return false;
        }

        try {
            return (bool) Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]))
                ->json('success', false);
        } catch (Throwable) {
            return false;
        }
    }
}
