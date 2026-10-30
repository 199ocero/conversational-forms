<?php

use JaOcero\ConversationalForms\Models\ConversationalForm;
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

];
