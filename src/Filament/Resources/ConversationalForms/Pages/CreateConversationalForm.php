<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages;

use Filament\Resources\Pages\CreateRecord;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;

/**
 * Asks only for a title and address; questions are added in the builder,
 * which opens right after.
 */
class CreateConversationalForm extends CreateRecord
{
    protected static string $resource = ConversationalFormResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
