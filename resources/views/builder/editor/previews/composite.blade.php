{{-- Contact info and address. --}}
<div class="cf-composite">
    <template x-for="part in parts(question)" x-bind:key="part.key">
        <div x-bind:class="{ 'cf-composite-part-half': part.width === 'half' }" class="cf-composite-part">
            <span class="cf-composite-label">
                <span x-text="part.label"></span><template x-if="part.isRequired"><span class="cf-question-required" aria-hidden="true">*</span></template>
            </span>
            <input type="text" x-bind:aria-label="part.label" autocomplete="off" class="cf-input cf-composite-input" />
        </div>
    </template>
</div>
