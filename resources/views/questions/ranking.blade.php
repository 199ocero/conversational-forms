@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $description = $getDescription();
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    <p id="{{ $id }}-instructions" class="cf-hint">{{ __('conversational-forms::conversational-forms.ranking.instructions') }}</p>

    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('ranking-question', ConversationalFormsServiceProvider::getAssetPackageName()) }}"
        x-data="rankingQuestionComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            items: @js($getItems()),
            translations: @js(__('conversational-forms::conversational-forms.ranking')),
        })"
        x-on:pointermove="drag($event)"
        x-on:pointerup="endDrag($event)"
        x-on:pointercancel="endDrag($event)"
        class="cf-ranking"
    >
        <ol
            role="list"
            aria-labelledby="{{ $id }}-label"
            aria-describedby="{{ collect([filled($description) ? "{$id}-description" : null, "{$id}-instructions"])->filter()->implode(' ') }}"
            class="cf-ranking-list"
        >
            <template x-for="(value, index) in order" x-bind:key="value">
                <li data-cf-rank-row x-bind:class="{ 'cf-grabbed': grabbed === value }" class="cf-ranking-item">
                    <span class="cf-ranking-handle" x-on:pointerdown="startDrag(value, $event)" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm-1 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm9-13a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm-1 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm1 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" /></svg>
                    </span>

                    <button
                        type="button"
                        x-bind:data-cf-rank-item="value"
                        x-bind:tabindex="index === 0 ? 0 : -1"
                        x-bind:aria-pressed="grabbed === value ? 'true' : 'false'"
                        x-bind:aria-label="`${position(value)}. ${label(value)}`"
                        x-on:keydown="handleKeydown(value, $event)"
                        x-on:pointerdown="startDrag(value, $event)"
                        class="cf-ranking-item-button"
                    >
                        <span class="cf-ranking-position" x-text="position(value)"></span>
                        <span class="cf-choice-label" x-text="label(value)"></span>
                    </button>

                    <span class="cf-ranking-moves">
                        <button type="button" tabindex="-1" x-on:click="moveBy(value, -1)" x-bind:disabled="index === 0" x-bind:aria-label="@js(__('conversational-forms::conversational-forms.ranking.move_up')).replace(':item', label(value))" class="cf-ranking-move">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9.47 6.47a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 1 1-1.06 1.06L10 8.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06l4.25-4.25Z" clip-rule="evenodd" /></svg>
                        </button>
                        <button type="button" tabindex="-1" x-on:click="moveBy(value, 1)" x-bind:disabled="index === order.length - 1" x-bind:aria-label="@js(__('conversational-forms::conversational-forms.ranking.move_down')).replace(':item', label(value))" class="cf-ranking-move">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                        </button>
                    </span>
                </li>
            </template>
        </ol>

        <div class="cf-sr-only" aria-live="assertive" x-text="announcement"></div>
    </div>
</x-conversational-forms::question>
