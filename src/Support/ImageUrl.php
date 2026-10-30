<?php

namespace JaOcero\ConversationalForms\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Images in definitions are either URLs or paths of files uploaded in the
 * builder (on the `conversational-forms.images.disk` disk).
 */
final class ImageUrl
{
    public static function resolve(?string $image): ?string
    {
        if (blank($image)) {
            return null;
        }

        if (preg_match('/^(https?:|data:|\/)/i', $image) === 1) {
            return $image;
        }

        return Storage::disk(config('conversational-forms.images.disk', 'public'))->url($image);
    }
}
