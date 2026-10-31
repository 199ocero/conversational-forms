{{-- Audio and video answers. --}}
<div class="cf-media">
    <div class="cf-media-start">
        <span class="cf-media-record">
            <span class="cf-media-record-dot" aria-hidden="true"></span>
            {{ __('conversational-forms::conversational-forms.media.record') }}
        </span>

        <template x-if="question.data?.settings?.allow_upload !== false">
            <span class="cf-media-upload">{{ __('conversational-forms::conversational-forms.media.upload_instead') }}</span>
        </template>
    </div>
</div>
