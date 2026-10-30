<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * The title and address of a conversation, used when creating one and in the
 * builder's settings tab.
 */
class ConversationalFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components());
    }

    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('title')
                ->label(__('conversational-forms::conversational-forms.builder.form.form_title'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                    if (($operation === 'create') && blank($get('slug'))) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')
                ->label(__('conversational-forms::conversational-forms.builder.form.slug'))
                ->helperText(__('conversational-forms::conversational-forms.builder.form.slug_help', ['url' => url(config('conversational-forms.routes.prefix', 'f')) . '/…']))
                ->required()
                ->maxLength(255)
                ->alphaDash()
                ->unique(ignoreRecord: true),
        ];
    }
}
