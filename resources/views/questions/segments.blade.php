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
            min: @js($getMinDate()),
            max: @js($getMaxDate()),
            locale: @js(str_replace('_', '-', app()->getLocale())),
            firstDayOfWeek: @js($hasCalendar() ? $getFirstDayOfWeek() : 0),
            shouldAutoAdvance: @js($shouldAutoAdvance()),
        })"
        role="group"
        aria-labelledby="{{ $id }}-label"
        @if (filled($describedBy)) aria-describedby="{{ $describedBy }}" @endif
        class="cf-segments"
    >
        @foreach ($segments as $segment)
            @php
                $inputId = $loop->first ? $id : "{$id}-{$segment['key']}";
                $hasOptions = filled($segment['options']);
                $isMeridiem = $segment['key'] === 'meridiem';
                $separator = $segment['separator'] ?? $getSeparator();
            @endphp

            @if ((! $loop->first) && filled($separator))
                <span class="cf-segments-separator" aria-hidden="true">{{ $separator }}</span>
            @endif

            <div class="cf-segment">
                <label for="{{ $inputId }}" id="{{ $inputId }}-segment-label" class="cf-segment-label">{{ $segment['label'] }}</label>

                <input
                    id="{{ $inputId }}"
                    type="text"
                    autocomplete="off"
                    placeholder="{{ $segment['placeholder'] }}"
                    @if ($isMeridiem)
                        {{-- Chosen with A / P, the arrow keys or the list; never typed into. --}}
                        readonly
                        inputmode="none"
                    @else
                        inputmode="numeric"
                        maxlength="{{ $segment['length'] }}"
                    @endif
                    x-bind:value="displayValue({{ $loop->index }})"
                    x-on:input="handleInput({{ $loop->index }}, $event)"
                    x-on:keydown="handleKeydown({{ $loop->index }}, $event)"
                    @if ($hasOptions)
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls="{{ $inputId }}-options"
                        x-bind:aria-expanded="openIndex === {{ $loop->index }} ? 'true' : 'false'"
                        x-bind:aria-activedescendant="openIndex === {{ $loop->index }} && activeIndex !== null ? `{{ $inputId }}-option-${activeIndex}` : null"
                        x-on:click="openOptions({{ $loop->index }})"
                        x-on:blur="openIndex === {{ $loop->index }} && closeOptions()"
                    @endif
                    @if ($hasError) aria-invalid="true" @endif
                    @if ($isRequired()) aria-required="true" @endif
                    @disabled($isDisabled())
                    data-cf-segment
                    style="--cf-segment-length: {{ $segment['length'] }}"
                    @class(['cf-input', 'cf-segment-input', 'cf-segment-meridiem' => $isMeridiem])
                />

                @if ($hasOptions)
                    {{-- Pressing on the list keeps focus in the box, so the list stays open. --}}
                    <ul
                        id="{{ $inputId }}-options"
                        role="listbox"
                        aria-labelledby="{{ $inputId }}-segment-label"
                        x-show="openIndex === {{ $loop->index }}"
                        x-cloak
                        x-on:mousedown.prevent
                        data-cf-segment-options
                        class="cf-dropdown-listbox cf-segment-listbox"
                    >
                        <template x-for="(option, optionIndex) in optionsFor({{ $loop->index }})" x-bind:key="option.value">
                            <li
                                role="option"
                                x-bind:id="`{{ $inputId }}-option-${optionIndex}`"
                                x-bind:aria-selected="isChosen({{ $loop->index }}, option) ? 'true' : 'false'"
                                x-bind:aria-disabled="isDisabled({{ $loop->index }}, option) ? 'true' : null"
                                x-bind:class="{
                                    'cf-active-option': activeIndex === optionIndex,
                                    'cf-selected': isChosen({{ $loop->index }}, option),
                                    'cf-disabled-option': isDisabled({{ $loop->index }}, option),
                                }"
                                x-on:click="chooseOption({{ $loop->index }}, option)"
                                x-on:mousemove="isDisabled({{ $loop->index }}, option) || (activeIndex = optionIndex)"
                                x-text="option.label"
                                class="cf-dropdown-option"
                            ></li>
                        </template>
                    </ul>
                @endif
            </div>
        @endforeach

        @if ($hasCalendar())
            <button
                type="button"
                x-ref="calendarButton"
                x-on:click="toggleCalendar()"
                aria-haspopup="dialog"
                aria-controls="{{ $id }}-calendar"
                x-bind:aria-expanded="isCalendarOpen ? 'true' : 'false'"
                aria-label="{{ __('conversational-forms::conversational-forms.questions.open_calendar') }}"
                data-cf-calendar-button
                class="cf-segments-calendar"
            >
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" /></svg>
            </button>

            {{-- The conversation leaves keys inside the calendar alone (`data-cf-keyboard-ignore`). --}}
            <div
                id="{{ $id }}-calendar"
                role="dialog"
                aria-label="{{ __('conversational-forms::conversational-forms.questions.calendar') }}"
                x-show="isCalendarOpen"
                x-cloak
                x-on:keydown="handleCalendarKeydown($event)"
                x-on:click.outside="$event.target.closest('[data-cf-calendar-button]') || closeCalendar(false)"
                x-on:focusout="$event.relatedTarget && ! $el.contains($event.relatedTarget) && $event.relatedTarget !== $refs.calendarButton && closeCalendar(false)"
                data-cf-keyboard-ignore
                class="cf-calendar"
            >
                <div class="cf-calendar-header">
                    <button
                        type="button"
                        x-on:click="showMonth(-1)"
                        x-bind:disabled="! canShowMonth(-1)"
                        aria-label="{{ __('conversational-forms::conversational-forms.questions.previous_month') }}"
                        class="cf-calendar-nav"
                    >
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" /></svg>
                    </button>

                    <p id="{{ $id }}-calendar-title" aria-live="polite" x-text="calendarTitle()" class="cf-calendar-title"></p>

                    <button
                        type="button"
                        x-on:click="showMonth(1)"
                        x-bind:disabled="! canShowMonth(1)"
                        aria-label="{{ __('conversational-forms::conversational-forms.questions.next_month') }}"
                        class="cf-calendar-nav"
                    >
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                    </button>
                </div>

                <table role="grid" aria-labelledby="{{ $id }}-calendar-title" class="cf-calendar-grid">
                    <thead>
                        <tr>
                            <template x-for="weekday in weekdays" x-bind:key="weekday.long">
                                <th scope="col" x-bind:abbr="weekday.long" x-text="weekday.short"></th>
                            </template>
                        </tr>
                    </thead>

                    <tbody>
                        {{-- Cells are keyed by position, so changing month relabels the same buttons and keyboard focus is never lost. --}}
                        <template x-for="(week, weekIndex) in calendarWeeks()" x-bind:key="weekIndex">
                            <tr>
                                <template x-for="(date, dayIndex) in week" x-bind:key="dayIndex">
                                    <td x-bind:aria-selected="date && isChosenDate(date) ? 'true' : null" x-bind:role="date ? 'gridcell' : null">
                                        <button
                                            type="button"
                                            x-show="date"
                                            x-bind:data-cf-date="date"
                                            x-bind:tabindex="date && date === calendarDate ? 0 : -1"
                                            x-bind:disabled="! date || isUnavailable(date)"
                                            x-bind:aria-label="date ? dayLabel(date) : null"
                                            x-bind:aria-current="date && isToday(date) ? 'date' : null"
                                            x-bind:class="{ 'cf-selected': date && isChosenDate(date), 'cf-today': date && isToday(date) }"
                                            x-on:click="date && chooseDate(date)"
                                            x-on:focus="date && (calendarDate = date)"
                                            x-text="date ? dayNumber(date) : ''"
                                            class="cf-calendar-day"
                                        ></button>
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-conversational-forms::question>
