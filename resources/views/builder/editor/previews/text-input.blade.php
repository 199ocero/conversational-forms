{{-- Short text, email, website, phone and number. --}}
<input
    x-bind:type="inputType(question)"
    x-bind:placeholder="placeholder(question)"
    x-bind:aria-label="question.data?.label ?? ''"
    autocomplete="off"
    class="cf-input"
/>
