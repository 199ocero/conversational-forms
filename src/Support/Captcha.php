<?php

namespace JaOcero\ConversationalForms\Support;

use InvalidArgumentException;
use JaOcero\ConversationalForms\Captcha\Turnstile;
use JaOcero\ConversationalForms\Contracts\CaptchaProvider;

/**
 * The captcha configured in `conversational-forms.captcha.provider`, if any.
 */
final class Captcha
{
    public static function provider(): ?CaptchaProvider
    {
        $provider = config('conversational-forms.captcha.provider');

        if (blank($provider)) {
            return null;
        }

        $provider = app(($provider === 'turnstile') ? Turnstile::class : $provider);

        if (! $provider instanceof CaptchaProvider) {
            throw new InvalidArgumentException('The captcha provider must implement [' . CaptchaProvider::class . '].');
        }

        return $provider;
    }
}
