@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $hasError = $errors->has($statePath);
    $description = $getDescription();
    $segments = $getSegments();
    $kind = $getKind();
    $describedBy = collect([filled($description) ? "{$id}-description" : null, $hasError ? "{$id}-error" : null])->filter()->implode(' ');
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('segments-question', ConversationalFormsServiceProvider::getAssetPackageName()) }}"
        x-data="segmentsQuestionComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            kind: @js($kind),
            segments: @js($segments),
            shouldAutoAdvance: @js($shouldAutoAdvance()),
        })"
        role="group"
        aria-labelledby="{{ $id }}-label"
        @if (filled($describedBy)) aria-describedby="{{ $describedBy }}" @endif
        class="cf-segments"
    >
        @foreach ($segments as $segment)
            @if (! $loop->first)
                <span class="cf-segments-separator" aria-hidden="true">{{ $getSeparator() }}</span>
            @endif

            <label class="cf-segment">
                <span class="cf-segment-label">{{ $segment['label'] }}</span>

                <input
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="{{ $segment['length'] }}"
                    placeholder="{{ $segment['placeholder'] }}"
                    x-bind:value="values[@js($segment['key'])] ?? ''"
                    x-on:input="handleInput({{ $loop->index }}, $event)"
                    x-on:keydown="handleKeydown({{ $loop->index }}, $event)"
                    @if ($loop->first) id="{{ $id }}" @endif
                    @if ($hasError) aria-invalid="true" @endif
                    @if ($isRequired()) aria-required="true" @endif
                    @disabled($isDisabled())
                    data-cf-segment
                    style="--cf-segment-length: {{ $segment['length'] }}"
                    class="cf-input cf-segment-input"
                />
            </label>
        @endforeach

        @if ($hasCalendar())
            <button
                type="button"
                x-on:click="openCalendar()"
                aria-label="{{ __('conversational-forms::conversational-forms.questions.open_calendar') }}"
                class="cf-segments-calendar"
            >
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" /></svg>
            </button>

            <input
                type="date"
                tabindex="-1"
                aria-hidden="true"
                @if (filled($min = $getMinDate())) min="{{ $min }}" @endif
                @if (filled($max = $getMaxDate())) max="{{ $max }}" @endif
                x-on:change="chooseFromCalendar($event)"
                data-cf-calendar
                class="cf-segments-picker"
            />
        @endif
    </div>
</x-conversational-forms::question>
