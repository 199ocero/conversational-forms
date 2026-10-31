@php
    use JaOcero\ConversationalForms\Builder\BuilderSchema;

    // `steps`, `steps.{uuid}.questions` or `endings`: the editor selects items by path.
    $path = BuilderSchema::editorPath($getStatePath());
@endphp

{{--
    The settings of each item: one at a time in the settings panel, or every
    step at once in the logic view. The outline adds, copies, deletes and
    reorders them.
--}}
<div
    data-cf-editor-items="{{ $path }}"
    data-cf-schema-component="{{ $getKey() }}"
    class="cf-editor-items"
>
    @foreach ($getItems() as $uuid => $item)
        @php($itemPath = "{$path}.{$uuid}")

        <div
            wire:key="{{ $item->getLivewireKey() }}.item"
            x-show="$store.cfEditor?.showsWithin(@js($itemPath))"
            x-cloak
            x-bind:class="{ 'cf-editor-logic-card-selected': $store.cfEditor?.view === 'logic' && $store.cfEditor?.is(@js($itemPath)) }"
            data-cf-editor-item="{{ $itemPath }}"
            @class(['cf-editor-item', 'cf-editor-item-nested' => str_contains($path, '.questions')])
        >
            @if (str_starts_with($path, 'steps'))
                {{-- In the logic view, which step these rules belong to. --}}
                <button
                    type="button"
                    x-show="$store.cfEditor?.view === 'logic'"
                    x-on:click="select(@js($itemPath))"
                    class="cf-editor-logic-card-header"
                    wire:ignore
                >
                    <span class="cf-editor-row-number" x-text="numberOf(@js($itemPath)) ?? ''"></span>
                    <span class="cf-editor-row-icon" x-html="typeIcon(itemType(@js($itemPath)))" aria-hidden="true"></span>
                    <span class="cf-editor-logic-card-title" x-text="titleOf(@js($itemPath))"></span>
                </button>
            @endif

            {{ $item }}
        </div>
    @endforeach
</div>
