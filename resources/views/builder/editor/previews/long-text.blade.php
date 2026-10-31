<div class="cf-editor-preview-control">
    <textarea
        x-bind:rows="question.data?.settings?.rows || 3"
        x-bind:placeholder="placeholder(question)"
        x-bind:aria-label="question.data?.label ?? ''"
        class="cf-input cf-textarea"
    ></textarea>

    <p class="cf-hint" aria-hidden="true">
        <span x-show="question.data?.settings?.submit_on_enter !== false">{!! __('conversational-forms::conversational-forms.questions.long_text_hint') !!}</span>
        <span x-show="question.data?.settings?.submit_on_enter === false">{!! __('conversational-forms::conversational-forms.questions.long_text_hint_without_submit') !!}</span>
    </p>
</div>
