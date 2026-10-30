<?php

namespace JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;
use JaOcero\ConversationalForms\Enums\FormStatus;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;
use JaOcero\ConversationalForms\Models\ConversationalForm;

class ConversationalFormsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(static::text('form_title'))
                    ->description(fn (ConversationalForm $record): string => '/' . config('conversational-forms.routes.prefix', 'f') . "/{$record->slug}")
                    ->searchable(['title', 'slug'])
                    ->sortable(),
                TextColumn::make('status')
                    ->label(static::text('status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('versions_count')
                    ->label(static::text('versions'))
                    ->counts('versions')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('completed_responses_count')
                    ->label(__('conversational-forms::conversational-forms.responses.title'))
                    ->counts(['responses as completed_responses_count' => fn ($query) => $query->where('status', 'completed')])
                    ->numeric()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label(static::text('published_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder(static::text('never'))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(static::text('updated_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(static::text('status'))->options(FormStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(static::text('open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (ConversationalForm $record): string => ConversationalFormsServiceProvider::getFormUrl($record), shouldOpenInNewTab: true)
                    ->visible(fn (ConversationalForm $record): bool => $record->isPublished()),
                Action::make('responses')
                    ->label(__('conversational-forms::conversational-forms.responses.title'))
                    ->icon(Heroicon::OutlinedInboxStack)
                    ->color('gray')
                    ->url(fn (ConversationalForm $record): string => ConversationalFormResource::getUrl('responses', ['record' => $record])),
                EditAction::make()->label(static::text('edit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function text(string $key): string
    {
        return __("conversational-forms::conversational-forms.builder.form.{$key}");
    }
}
