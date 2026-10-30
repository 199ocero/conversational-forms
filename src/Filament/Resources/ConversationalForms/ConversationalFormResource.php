<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms;

use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use JaOcero\ConversationalForms\ConversationalFormsPlugin;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\CreateConversationalForm;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\EditConversationalForm;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\ListConversationalForms;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\ManageResponses;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\ViewLogicMap;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Schemas\ConversationalFormForm;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Tables\ConversationalFormsTable;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Support\Models;

class ConversationalFormResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'title';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    /**
     * @return class-string<ConversationalForm>
     */
    public static function getModel(): string
    {
        return Models::form();
    }

    public static function getModelLabel(): string
    {
        return __('conversational-forms::conversational-forms.builder.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('conversational-forms::conversational-forms.builder.resource.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return static::plugin()?->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()?->getNavigationSort() ?? parent::getNavigationSort();
    }

    public static function form(Schema $schema): Schema
    {
        return ConversationalFormForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConversationalFormsTable::configure($table);
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversationalForms::route('/'),
            'create' => CreateConversationalForm::route('/create'),
            'edit' => EditConversationalForm::route('/{record}/edit'),
            'logic' => ViewLogicMap::route('/{record}/logic'),
            'responses' => ManageResponses::route('/{record}/responses'),
        ];
    }

    /**
     * Builder, logic map and responses, as tabs on each conversation.
     *
     * @return array<NavigationItem>
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditConversationalForm::class,
            ViewLogicMap::class,
            ManageResponses::class,
        ]);
    }

    protected static function plugin(): ?ConversationalFormsPlugin
    {
        $panel = filament()->getCurrentOrDefaultPanel();

        return ($panel?->hasPlugin('conversational-forms') ?? false) ? ConversationalFormsPlugin::get() : null;
    }
}
