<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;

class ListConversationalForms extends ListRecords
{
    protected static string $resource = ConversationalFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
