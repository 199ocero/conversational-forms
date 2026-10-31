{{-- Multiple choice. --}}
<div class="cf-editor-preview-control">
    <template x-if="selectionHint(question)">
        <p class="cf-hint" x-text="selectionHint(question)"></p>
    </template>

    <div class="cf-choices">
        <template x-for="option in options(question, 'options', { withEmpty: true })" x-bind:key="option.uuid">
            {{-- Not a button: the label inside is edited in place. --}}
            <div
                x-on:click="choose(question, option.value, question.data?.settings?.multiple)"
                x-bind:class="{ 'cf-selected': isChosen(question, option.value) }"
                x-bind:data-cf-editor-option="option.uuid"
                class="cf-choice"
            >
                <kbd class="cf-choice-key" aria-hidden="true" x-text="option.key"></kbd>
                <span class="cf-choice-label">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'question.path', 'field' => '`settings.options.${option.uuid}.label`', 'isExpression' => true, 'label' => __('conversational-forms::conversational-forms.builder.settings.option_label'), 'placeholder' => __('conversational-forms::conversational-forms.builder.editor.placeholders.option'), 'inline' => true])</span>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="cf-choice-check"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                <button
                    type="button"
                    x-show="options(question, 'options', { withEmpty: true }).length > 1"
                    x-on:click.stop="removeOption(question, option.uuid)"
                    title="{{ __('conversational-forms::conversational-forms.builder.editor.remove_choice') }}"
                    class="cf-editor-option-remove"
                >
                    <x-filament::icon icon="heroicon-m-x-mark" />
                    <span class="cf-sr-only">{{ __('conversational-forms::conversational-forms.builder.editor.remove_choice') }}</span>
                </button>
            </div>
        </template>

        <template x-if="question.data?.settings?.other">
            <div x-bind:class="{ 'cf-selected': isChosen(question, '__other') }" class="cf-choice cf-choice-other">
                <button type="button" x-on:click="choose(question, '__other', question.data?.settings?.multiple)" class="cf-choice-other-toggle">
                    <kbd class="cf-choice-key" aria-hidden="true" x-text="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[options(question).length] ?? ''"></kbd>
                    <span class="cf-choice-label">{{ __('conversational-forms::conversational-forms.questions.other') }}</span>
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="cf-choice-check"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                </button>
            </div>
        </template>
    </div>

    <button type="button" x-on:click="addOption(question)" class="cf-editor-add-option">
        <x-filament::icon icon="heroicon-m-plus" />
        {{ __('conversational-forms::conversational-forms.builder.editor.add_choice') }}
    </button>
</div>
