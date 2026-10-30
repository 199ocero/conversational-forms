<?php

namespace JaOcero\ConversationalForms\Contracts;

use Illuminate\Contracts\Support\Htmlable;

/**
 * A captcha checked before a public conversation is stored. The widget hands
 * its token to the page with `$wire.set('captchaToken', token)`.
 */
interface CaptchaProvider
{
    /**
     * The widget, shown at the bottom of the public page.
     */
    public function render(): Htmlable;

    public function verify(?string $token, ?string $ip): bool;
}
