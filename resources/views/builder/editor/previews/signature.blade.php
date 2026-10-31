<div class="cf-signature">
    <div class="cf-signature-pad">
        <span class="cf-signature-placeholder" aria-hidden="true">{{ __('conversational-forms::conversational-forms.signature.draw_here') }}</span>
    </div>

    <div class="cf-signature-actions">
        <span class="cf-link-button">{{ __('conversational-forms::conversational-forms.signature.clear') }}</span>
        <template x-if="question.data?.settings?.typing !== false">
            <span class="cf-link-button">{{ __('conversational-forms::conversational-forms.signature.type_instead') }}</span>
        </template>
    </div>
</div>
