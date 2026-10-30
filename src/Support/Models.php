<?php

namespace JaOcero\ConversationalForms\Support;

use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Models\ConversationalFormVersion;

/**
 * The model classes configured in `conversational-forms.models`.
 */
final class Models
{
    /**
     * @return class-string<ConversationalForm>
     */
    public static function form(): string
    {
        return config('conversational-forms.models.form', ConversationalForm::class);
    }

    /**
     * @return class-string<ConversationalFormVersion>
     */
    public static function version(): string
    {
        return config('conversational-forms.models.version', ConversationalFormVersion::class);
    }
}
