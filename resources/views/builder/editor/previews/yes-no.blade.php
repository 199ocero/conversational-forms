{{-- Yes / No and legal consent. --}}
<div class="cf-editor-preview-control">
    <template x-if="question.type === 'legal_consent' && question.data?.settings?.terms">
        <div class="cf-terms" x-text="question.data.settings.terms"></div>
    </template>

    <div class="cf-choices cf-choices-inline">
        <template x-for="option in yesNo(question)" x-bind:key="option.key">
            <button
                type="button"
                x-on:click="choose(question, option.value)"
                x-bind:class="{ 'cf-selected': isChosen(question, option.value) }"
                class="cf-choice"
            >
                <kbd class="cf-choice-key" aria-hidden="true" x-text="option.key"></kbd>
                <span class="cf-choice-label" x-text="option.label"></span>
            </button>
        </template>
    </div>
</div>
