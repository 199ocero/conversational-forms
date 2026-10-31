<div class="cf-upload">
    <div class="cf-dropzone">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="cf-dropzone-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
        <span
            class="cf-dropzone-text"
            x-text="question.data?.settings?.multiple ? @js(__('conversational-forms::conversational-forms.upload.choose_files')) : @js(__('conversational-forms::conversational-forms.upload.choose_file'))"
        ></span>
        <template x-if="question.data?.settings?.max_size">
            <span class="cf-hint" x-text="@js(__('conversational-forms::conversational-forms.upload.max_size')).replace(':size', `${question.data.settings.max_size} KB`)"></span>
        </template>
    </div>
</div>
