@php
    $text = fn (string $key): string => __("conversational-forms::conversational-forms.builder.editor.{$key}");
@endphp

<div class="cf-editor-outline-inner">
    {{-- The content, in the preview, or the logic of every step. --}}
    <div class="cf-editor-view-switch" role="tablist">
        @foreach (['content', 'logic'] as $view)
            <button
                type="button"
                role="tab"
                x-on:click="setView(@js($view))"
                x-bind:aria-selected="view === @js($view) ? 'true' : 'false'"
                class="cf-editor-view-switch-button"
            >
                {{ $text("view_{$view}") }}
            </button>
        @endforeach
    </div>

    <div class="cf-editor-outline-section">
        <button
            type="button"
            data-cf-editor-row="welcome"
            x-on:click="select('welcome')"
            x-bind:class="rowClasses('welcome')"
            x-bind:aria-current="selected === 'welcome' ? 'true' : 'false'"
            class="cf-editor-row"
        >
            <x-filament::icon icon="heroicon-o-hand-raised" class="cf-editor-row-icon" />
            <span class="cf-editor-row-title">{{ $text('welcome') }}</span>
            <span x-show="! state.welcome?.enabled" class="cf-editor-badge">{{ $text('welcome_off') }}</span>
        </button>
    </div>

    <div class="cf-editor-outline-section">
        <div class="cf-editor-outline-heading">
            <h3>{{ $text('steps') }}</h3>

            <button
                type="button"
                x-on:click="openPicker('steps')"
                x-bind:disabled="isBusy"
                x-bind:aria-expanded="picker === 'steps' ? 'true' : 'false'"
                class="cf-editor-add"
            >
                <x-filament::icon icon="heroicon-m-plus" class="cf-editor-add-icon" />
                {{ $text('add') }}
            </button>
        </div>

        <ol class="cf-editor-rows">
            <template x-for="item in outlineItems" x-bind:key="item.path">
                <li
                    x-on:dragover="dragOver($event, item.path)"
                    x-on:dragleave="dropTarget === item.path && (dropTarget = null)"
                    x-on:drop="drop($event, item.path)"
                    x-bind:class="{ 'cf-editor-rows-item-nested': item.depth > 0 }"
                    class="cf-editor-rows-item"
                >
                    <div
                        x-bind:class="rowClasses(item.path)"
                        class="cf-editor-row cf-editor-row-with-actions"
                    >
                        <span
                            draggable="true"
                            x-on:dragstart="startDrag($event, item.path)"
                            x-on:dragend="endDrag()"
                            x-on:click="select(item.path)"
                            title="{{ $text('drag') }}"
                            class="cf-editor-row-handle"
                            aria-hidden="true"
                        >
                            <x-filament::icon icon="heroicon-m-bars-2" />
                        </span>

                        <button
                            type="button"
                            x-bind:data-cf-editor-row="item.path"
                            x-on:click="select(item.path)"
                            x-bind:aria-current="selected === item.path ? 'true' : 'false'"
                            aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"
                            class="cf-editor-row-button"
                        >
                            <span class="cf-editor-row-number" x-text="item.number ?? ''"></span>
                            <span class="cf-editor-row-icon" x-html="typeIcon(item.type)" aria-hidden="true"></span>
                            <span class="cf-sr-only" x-text="typeLabel(item.type)"></span>
                            <span class="cf-editor-row-text">
                                <span
                                    x-text="item.title || @js($text('untitled'))"
                                    x-bind:class="{ 'cf-editor-row-untitled': ! item.title }"
                                    class="cf-editor-row-title"
                                ></span>

                                {{-- What's set up on this step, as words under its title: not buttons. --}}
                                <template x-if="hasLogic(item.path) || isConditional(item.path)">
                                    <span class="cf-editor-row-meta">
                                        <span x-show="hasLogic(item.path)" title="{{ $text('has_logic') }}" class="cf-editor-row-meta-item">
                                            <x-filament::icon icon="heroicon-m-arrow-turn-down-right" />
                                            {{ $text('tag_logic') }}
                                        </span>
                                        <span x-show="isConditional(item.path)" title="{{ $text('conditional') }}" class="cf-editor-row-meta-item">
                                            <x-filament::icon icon="heroicon-m-eye-slash" />
                                            {{ $text('tag_conditional') }}
                                        </span>
                                    </span>
                                </template>
                            </span>
                            <span
                                x-show="needsAttention(item)"
                                x-bind:title="item.isMissingKey ? @js($text('needs_key')) : @js($text('needs_attention'))"
                                class="cf-editor-row-warning"
                            ></span>
                        </button>

                        <span class="cf-editor-row-actions">
                            <template x-if="item.type === 'group'">
                                <button
                                    type="button"
                                    x-on:click="openPicker(`${item.path}.questions`)"
                                    x-bind:disabled="isBusy"
                                    title="{{ $text('add_to_group') }}"
                                    class="cf-editor-icon-button"
                                >
                                    <x-filament::icon icon="heroicon-m-plus" />
                                    <span class="cf-sr-only">{{ $text('add_to_group') }}</span>
                                </button>
                            </template>

                            <button
                                type="button"
                                x-on:click="duplicate(item.path)"
                                x-bind:disabled="isBusy"
                                title="{{ $text('duplicate') }}"
                                class="cf-editor-icon-button"
                            >
                                <x-filament::icon icon="heroicon-m-square-2-stack" />
                                <span class="cf-sr-only">{{ $text('duplicate') }}</span>
                            </button>

                            <button
                                type="button"
                                x-show="canDelete(item.path)"
                                x-on:click="remove(item.path)"
                                x-bind:disabled="isBusy"
                                x-bind:title="confirmingDelete === item.path ? @js($text('delete_confirm')) : @js($text('delete'))"
                                x-bind:class="{ 'cf-editor-icon-button-confirm': confirmingDelete === item.path }"
                                class="cf-editor-icon-button cf-editor-icon-button-danger"
                            >
                                <x-filament::icon icon="heroicon-m-trash" />
                                <span class="cf-sr-only" x-text="confirmingDelete === item.path ? @js($text('delete_confirm')) : @js($text('delete'))"></span>
                            </button>
                        </span>
                    </div>
                </li>
            </template>
        </ol>

        <p x-show="isEmpty" class="cf-editor-empty">{{ $text('empty_description') }}</p>
    </div>

    <div class="cf-editor-outline-section">
        <div class="cf-editor-outline-heading">
            <h3>{{ $text('endings') }}</h3>

            <button type="button" x-on:click="addEnding()" x-bind:disabled="isBusy" class="cf-editor-add">
                <x-filament::icon icon="heroicon-m-plus" class="cf-editor-add-icon" />
                {{ $text('add') }}
                <span class="cf-sr-only">{{ $text('add_ending') }}</span>
            </button>
        </div>

        <ol class="cf-editor-rows">
            <template x-for="item in endingItems" x-bind:key="item.path">
                <li
                    x-on:dragover="dragOver($event, item.path)"
                    x-on:dragleave="dropTarget === item.path && (dropTarget = null)"
                    x-on:drop="drop($event, item.path)"
                    class="cf-editor-rows-item"
                >
                    <div x-bind:class="rowClasses(item.path)" class="cf-editor-row cf-editor-row-with-actions">
                        <span
                            draggable="true"
                            x-on:dragstart="startDrag($event, item.path)"
                            x-on:dragend="endDrag()"
                            x-on:click="select(item.path)"
                            title="{{ $text('drag') }}"
                            class="cf-editor-row-handle"
                            aria-hidden="true"
                        >
                            <x-filament::icon icon="heroicon-m-bars-2" />
                        </span>

                        <button
                            type="button"
                            x-bind:data-cf-editor-row="item.path"
                            x-on:click="select(item.path)"
                            x-bind:aria-current="selected === item.path ? 'true' : 'false'"
                            class="cf-editor-row-button"
                        >
                            <span class="cf-editor-row-icon" x-html="endingIcon(item.type)" aria-hidden="true"></span>
                            <span
                                x-text="item.title || item.key || @js($text('untitled'))"
                                class="cf-editor-row-title"
                            ></span>
                            <span x-show="item.key" x-text="item.key" class="cf-editor-badge"></span>
                            <span x-show="needsAttention(item)" class="cf-editor-row-warning" title="{{ $text('needs_attention') }}"></span>
                        </button>

                        <span class="cf-editor-row-actions">
                            <button
                                type="button"
                                x-show="canDelete(item.path)"
                                x-on:click="remove(item.path)"
                                x-bind:disabled="isBusy"
                                x-bind:title="confirmingDelete === item.path ? @js($text('delete_confirm')) : @js($text('delete'))"
                                x-bind:class="{ 'cf-editor-icon-button-confirm': confirmingDelete === item.path }"
                                class="cf-editor-icon-button cf-editor-icon-button-danger"
                            >
                                <x-filament::icon icon="heroicon-m-trash" />
                                <span class="cf-sr-only" x-text="confirmingDelete === item.path ? @js($text('delete_confirm')) : @js($text('delete'))"></span>
                            </button>
                        </span>
                    </div>
                </li>
            </template>
        </ol>
    </div>

    <div class="cf-editor-outline-section cf-editor-outline-footer">
        @foreach (['design' => 'heroicon-o-swatch', 'settings' => 'heroicon-o-cog-6-tooth'] as $path => $icon)
            <button
                type="button"
                data-cf-editor-row="{{ $path }}"
                data-cf-editor-opens-panel
                x-on:click="select(@js($path))"
                x-bind:class="rowClasses(@js($path))"
                x-bind:aria-current="selected === @js($path) ? 'true' : 'false'"
                class="cf-editor-row"
            >
                <x-filament::icon :icon="$icon" class="cf-editor-row-icon" />
                <span class="cf-editor-row-title">{{ $text($path) }}</span>
            </button>
        @endforeach
    </div>

    {{-- The question types to add, grouped, with a search. --}}
    <div
        x-show="picker"
        x-transition.opacity.duration.100ms
        x-on:click.self="picker = null"
        class="cf-editor-picker-backdrop"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-label="{{ $text('add_question') }}"
            x-trap.noscroll="picker"
            class="cf-editor-picker"
        >
            <input
                type="search"
                x-model="pickerQuery"
                data-cf-editor-picker-search
                placeholder="{{ $text('search_types') }}"
                aria-label="{{ $text('search_types') }}"
                x-on:keydown.enter.prevent="pickerGroups[0]?.types[0] && addStep(pickerGroups[0].types[0].name)"
                class="cf-editor-picker-search"
            />

            <div class="cf-editor-picker-groups">
                <template x-for="group in pickerGroups" x-bind:key="group.group">
                    <div x-bind:data-group="group.group" class="cf-editor-picker-group">
                        <h4 x-text="group.label"></h4>

                        <div class="cf-editor-picker-types">
                            <template x-for="type in group.types" x-bind:key="type.name">
                                <button
                                    type="button"
                                    x-on:click="addStep(type.name)"
                                    x-bind:style="{ '--cf-picker-index': type.index }"
                                    class="cf-editor-picker-type"
                                >
                                    <span class="cf-editor-picker-icon" x-html="type.icon" aria-hidden="true"></span>
                                    <span x-text="type.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <p x-show="! pickerGroups.length" class="cf-editor-empty">{{ $text('no_types') }}</p>
            </div>
        </div>
    </div>
</div>
