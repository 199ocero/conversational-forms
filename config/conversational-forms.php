<?php

use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Models\ConversationalFormResponse;
use JaOcero\ConversationalForms\Models\ConversationalFormVersion;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The models used for conversations made in the builder. Extend the
    | package's models to add relationships or scopes, and point to them here.
    |
    */

    'models' => [
        'form' => ConversationalForm::class,
        'version' => ConversationalFormVersion::class,
        'response' => ConversationalFormResponse::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Responses
    |--------------------------------------------------------------------------
    |
    | Answers to builder-made conversations are stored, and saved as partial
    | after each step so drop-off can be measured. Partial responses older
    | than this many days are deleted by `php artisan model:prune` (`null`
    | keeps them).
    |
    */

    'responses' => [
        'store_partials' => true,
        'prune_partials_after_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam protection
    |--------------------------------------------------------------------------
    |
    | Public pages ignore submissions that fill in a hidden "honeypot" field
    | or arrive faster than a person could answer, and limit how many
    | requests one visitor can make per minute. A captcha can be added with
    | a provider such as Cloudflare Turnstile.
    |
    */

    'spam' => [
        'honeypot' => true,
        'minimum_seconds' => 3,
        'requests_per_minute' => 120,
    ],

    'captcha' => [
        'provider' => env('CONVERSATIONAL_FORMS_CAPTCHA'), // `turnstile` or a CaptchaProvider class
        'turnstile' => [
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public pages
    |--------------------------------------------------------------------------
    |
    | Builder-made conversations are answered at `/{prefix}/{slug}`. Turn the
    | routes off to register your own with `ConversationalFormPage`.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'f',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    |
    | Themes can use a font from Bunny Fonts, a privacy-friendly host of the
    | Google Fonts families. Set the URL to `null` to never load fonts from
    | another site; themes then use the system font.
    |
    */

    'fonts' => [
        'url' => 'https://fonts.bunny.net/css',
    ],

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | Where images uploaded in the builder (picture choices, question media)
    | are stored. They are shown on public pages, so the disk must be public.
    |
    */

    'images' => [
        'disk' => env('CONVERSATIONAL_FORMS_IMAGES_DISK', 'public'),
        'directory' => 'conversational-forms/images',
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Where files and signatures sent through builder-made conversations are
    | stored. They are private by default; a `null` disk uses the app's default.
    |
    */

    'uploads' => [
        'disk' => env('CONVERSATIONAL_FORMS_UPLOADS_DISK'),
        'directory' => 'conversational-forms/uploads',
        'visibility' => 'private',
    ],

    /*
    |--------------------------------------------------------------------------
    | Builder
    |--------------------------------------------------------------------------
    |
    | The builder saves the draft by itself once nothing has changed for this
    | many seconds. Set it to `null` to save only with the Save button.
    |
    */

    'builder' => [
        'autosave_seconds' => 3,
    ],

];
