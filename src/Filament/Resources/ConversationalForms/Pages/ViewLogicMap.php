<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use JaOcero\ConversationalForms\Builder\LogicMap;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;
use JaOcero\ConversationalForms\Models\ConversationalForm;

/**
 * Every step and ending of the draft, with the default flow and the jumps
 * and endings chosen by logic, and warnings for anything unreachable.
 *
 * @property-read ConversationalForm $record
 */
class ViewLogicMap extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ConversationalFormResource::class;

    protected string $view = 'conversational-forms::builder.logic-map';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShare;

    public static function getNavigationLabel(): string
    {
        return __('conversational-forms::conversational-forms.builder.form.logic_map');
    }

    public function getBreadcrumb(): string
    {
        return __('conversational-forms::conversational-forms.builder.form.logic_map');
    }

    protected Width | string | null $maxContentWidth = Width::FiveExtraLarge;

    public function mount(int | string $record): void
    {
        /** @var ConversationalForm $form */
        $form = $this->resolveRecord($record);

        $this->record = $form;

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getTitle(): string | Htmlable
    {
        return __('conversational-forms::conversational-forms.builder.logic.title', ['title' => $this->record->title]);
    }

    public function getSubheading(): string | Htmlable | null
    {
        return __('conversational-forms::conversational-forms.builder.logic.subheading');
    }

    public function getLogicMap(): LogicMap
    {
        return LogicMap::fromDefinition($this->record->definition ?? []);
    }
}
