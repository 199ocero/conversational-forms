{{-- The draft in its public page. The link is signed and expires, so it only works from the builder. --}}
<div class="fi-sc-text" style="height: calc(100dvh - 12rem); min-height: 32rem">
    <iframe
        src="{{ $url }}"
        title="{{ __('conversational-forms::conversational-forms.builder.form.preview') }}"
        style="width: 100%; height: 100%; border: 0; border-radius: 0.75rem; box-shadow: 0 0 0 1px rgb(0 0 0 / 0.1)"
    ></iframe>
</div>
