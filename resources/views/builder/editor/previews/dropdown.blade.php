<div x-data="{ isOpen: false }" x-on:click.outside="isOpen = false" class="cf-dropdown">
    <input
        type="text"
        readonly
        x-on:click="isOpen = ! isOpen"
        x-bind:value="options(question).find((option) => isChosen(question, option.value))?.label ?? ''"
        placeholder="{{ __('conversational-forms::conversational-forms.questions.dropdown_placeholder') }}"
        class="cf-input cf-dropdown-input"
    />

    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="cf-dropdown-chevron"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>

    <ul x-show="isOpen" x-cloak class="cf-dropdown-listbox">
        <template x-for="option in options(question)" x-bind:key="option.value">
            <li
                x-on:click="choose(question, option.value); isOpen = false"
                x-bind:class="{ 'cf-selected': isChosen(question, option.value) }"
                x-text="option.label"
                class="cf-dropdown-option"
            ></li>
        </template>
    </ul>
</div>
