<div class="cf-matrix" x-bind:style="{ '--cf-matrix-columns': options(question, 'answers').length }">
    <div class="cf-matrix-header" aria-hidden="true">
        <span></span>
        <template x-for="column in options(question, 'answers')" x-bind:key="column.value">
            <span class="cf-matrix-column-label" x-text="column.label"></span>
        </template>
    </div>

    <template x-for="row in options(question, 'rows')" x-bind:key="row.value">
        <fieldset class="cf-matrix-row">
            <legend class="cf-matrix-row-label" x-text="row.label"></legend>
            <template x-for="column in options(question, 'answers')" x-bind:key="column.value">
                <label class="cf-matrix-cell">
                    <input
                        x-bind:type="question.data?.settings?.multiple ? 'checkbox' : 'radio'"
                        x-bind:name="`${question.path}-${row.value}`"
                        class="cf-matrix-input"
                    />
                    <span class="cf-matrix-cell-label" x-text="column.label"></span>
                </label>
            </template>
        </fieldset>
    </template>
</div>
