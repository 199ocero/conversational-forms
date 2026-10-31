{{-- Opinion scale and NPS. --}}
<div class="cf-editor-preview-control">
    <div class="cf-scale" x-bind:style="{ '--cf-scale-steps': scale(question).length }">
        <template x-for="value in scale(question)" x-bind:key="value">
            <button
                type="button"
                x-on:click="choose(question, value)"
                x-bind:class="{ 'cf-selected': isChosen(question, value) }"
                x-text="value"
                class="cf-scale-option"
            ></button>
        </template>
    </div>

    <template x-if="scaleLabels(question).start || scaleLabels(question).end">
        <div class="cf-scale-labels">
            <span x-text="scaleLabels(question).start ? `${scale(question)[0]}: ${scaleLabels(question).start}` : ''"></span>
            <span x-text="scaleLabels(question).end ? `${scale(question).at(-1)}: ${scaleLabels(question).end}` : ''"></span>
        </div>
    </template>
</div>
