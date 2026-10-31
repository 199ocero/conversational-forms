{{--
    Text edited in place in the preview: shown as it will look (with piping
    tokens), and turned into a field with the same type when clicked.

    $path        JavaScript expression of the item's editor path (`preview.path`, `question.path`).
    $field       The field, as written in the item's state: `label`, `settings.options.{uuid}.label`.
                 A JavaScript expression when `$isExpression` is set.
    $label       Accessible name of the field.
    $placeholder Shown while the text is empty.
    $multiline   Enter adds a line instead of finishing.
    $inline      For short text inside a line, such as a button.
--}}
@php
    $field = ($isExpression ?? false) ? $field : \Illuminate\Support\Js::from($field);
    $multiline ??= false;
    $inline ??= false;
@endphp

<span
    x-data="{ isEditing: false }"
    x-bind:class="{
        'cf-editor-editable-editing': isEditing,
        'cf-editor-editable-invalid': hasFieldError({{ $path }}, {{ $field }}),
    }"
    @class(['cf-editor-editable', 'cf-editor-editable-inline' => $inline])
>
    <template x-if="! isEditing">
        <span
            role="button"
            tabindex="0"
            x-on:click.stop.prevent="isEditing = true; startEditing({{ $path }})"
            x-on:keydown.enter.prevent.stop="isEditing = true; startEditing({{ $path }})"
            aria-label="{{ $label }}"
            title="{{ $label }}"
            class="cf-editor-editable-text"
        >
            <template x-if="textAt({{ $path }}, {{ $field }})">
                <span>@include('conversational-forms::builder.editor.previews.piped', ['text' => "textAt({$path}, {$field})"])</span>
            </template>
            <template x-if="! textAt({{ $path }}, {{ $field }})">
                <span class="cf-editor-editable-placeholder">{{ $placeholder }}</span>
            </template>
        </span>
    </template>

    <template x-if="isEditing">
        @if ($inline)
            <input
                type="text"
                x-init="$el.value = textAt({{ $path }}, {{ $field }}) ?? ''; $nextTick(() => { $el.focus(); $el.select() })"
                x-bind:size="Math.max(($el.value || $el.placeholder).length, 1)"
                x-on:input="setText({{ $path }}, {{ $field }}, $event.target.value); $el.size = Math.max(($el.value || $el.placeholder).length, 1)"
                x-on:click.stop
                x-on:blur="isEditing = false"
                x-on:keydown.enter.prevent.stop="$el.blur()"
                x-on:keydown.escape.prevent.stop="$el.blur()"
                placeholder="{{ $placeholder }}"
                aria-label="{{ $label }}"
                class="cf-editor-editable-input"
            />
        @else
            <textarea
                rows="1"
                x-init="$el.value = textAt({{ $path }}, {{ $field }}) ?? ''; $nextTick(() => { fitText($el); $el.focus(); $el.setSelectionRange($el.value.length, $el.value.length) })"
                x-on:input="setText({{ $path }}, {{ $field }}, $event.target.value); fitText($el)"
                x-on:click.stop
                x-on:blur="isEditing = false"
                @unless ($multiline)
                    x-on:keydown.enter.prevent.stop="$el.blur()"
                @endunless
                x-on:keydown.escape.prevent.stop="$el.blur()"
                placeholder="{{ $placeholder }}"
                aria-label="{{ $label }}"
                class="cf-editor-editable-input"
            ></textarea>
        @endif
    </template>
</span>
