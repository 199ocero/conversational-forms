<div class="cf-editor-preview-control">
    <p class="cf-hint">{{ __('conversational-forms::conversational-forms.ranking.instructions') }}</p>

    <div class="cf-ranking">
        <ol class="cf-ranking-list">
            <template x-for="(option, index) in options(question, 'options', { withEmpty: true })" x-bind:key="option.uuid">
                <li x-bind:data-cf-editor-option="option.uuid" class="cf-ranking-item">
                    <span class="cf-ranking-handle" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm-1 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm9-13a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm-1 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm1 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" /></svg>
                    </span>
                    <span class="cf-ranking-item-button">
                        <span class="cf-ranking-position" x-text="index + 1"></span>
                        <span class="cf-choice-label">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'question.path', 'field' => '`settings.options.${option.uuid}.label`', 'isExpression' => true, 'label' => __('conversational-forms::conversational-forms.builder.settings.option_label'), 'placeholder' => __('conversational-forms::conversational-forms.builder.editor.placeholders.option'), 'inline' => true])</span>
                    </span>
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
                </li>
            </template>
        </ol>
    </div>

    <button type="button" x-on:click="addOption(question)" class="cf-editor-add-option">
        <x-filament::icon icon="heroicon-m-plus" />
        {{ __('conversational-forms::conversational-forms.builder.editor.add_choice') }}
    </button>
</div>
