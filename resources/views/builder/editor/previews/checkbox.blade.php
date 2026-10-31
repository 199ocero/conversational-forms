<div class="cf-choices">
    <button
        type="button"
        x-on:click="choose(question, true)"
        x-bind:class="{ 'cf-selected': isChosen(question, true) }"
        class="cf-choice cf-checkbox"
    >
        <span class="cf-checkbox-box" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="currentColor" class="cf-checkbox-check"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
        </span>
        <span class="cf-choice-label" x-text="question.data?.settings?.checkbox_label || question.data?.label || ''"></span>
    </button>
</div>
