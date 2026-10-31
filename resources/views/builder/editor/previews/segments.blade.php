{{-- Date and time boxes. --}}
<div class="cf-segments">
    <template x-for="(segment, index) in segments(question)" x-bind:key="segment.key">
        <div class="cf-editor-segment-wrapper">
            <template x-if="segment.separator">
                <span class="cf-segments-separator" aria-hidden="true" x-text="segment.separator"></span>
            </template>
            <div class="cf-segment">
                <span class="cf-segment-label" x-text="segment.label"></span>
                <input
                    type="text"
                    inputmode="numeric"
                    x-bind:maxlength="segment.length"
                    x-bind:placeholder="segment.placeholder"
                    x-bind:aria-label="segment.label"
                    x-bind:style="{ '--cf-segment-length': segment.length }"
                    x-bind:class="{ 'cf-segment-meridiem': segment.key === 'meridiem' }"
                    class="cf-input cf-segment-input"
                />
            </div>
        </div>
    </template>

    <template x-if="hasCalendar(question)">
        <span class="cf-segments-calendar" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" /></svg>
        </span>
    </template>
</div>
