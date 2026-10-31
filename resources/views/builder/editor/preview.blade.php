@php
    use JaOcero\ConversationalForms\Definitions\QuestionTypeRegistry;

    $text = fn (string $key): string => __("conversational-forms::conversational-forms.builder.editor.{$key}");
    $field = fn (string $key): string => __("conversational-forms::conversational-forms.builder.form.{$key}");
    $placeholder = fn (string $key): string => __("conversational-forms::conversational-forms.builder.editor.placeholders.{$key}");

    $previewViews = collect(app(QuestionTypeRegistry::class)->all())
        ->map(fn ($type): ?string => $type->getPreviewView())
        ->filter();
@endphp

<div class="cf-editor-preview-inner">
    {{-- One bar: the device, the selected step, and its logic and settings. Steps are chosen in the outline. --}}
    <div class="cf-editor-preview-toolbar" role="toolbar" aria-label="{{ $text('preview') }}">
        <div class="cf-editor-segmented" role="group" aria-label="{{ $text('preview') }}">
            @foreach (['desktop' => 'heroicon-m-computer-desktop', 'mobile' => 'heroicon-m-device-phone-mobile'] as $device => $icon)
                <button
                    type="button"
                    x-on:click="device = @js($device)"
                    x-bind:aria-pressed="device === @js($device) ? 'true' : 'false'"
                    title="{{ $text($device) }}"
                    class="cf-editor-segmented-button"
                >
                    <x-filament::icon :icon="$icon" />
                    <span class="cf-sr-only">{{ $text($device) }}</span>
                </button>
            @endforeach
        </div>

        <span class="cf-editor-step-toolbar-type">
            <template x-if="toolbarPath">
                <span class="cf-editor-step-toolbar-type-inner">
                    <span class="cf-editor-row-icon" x-html="selectionIcon" aria-hidden="true"></span>
                    <span x-text="selectionTitle"></span>
                </span>
            </template>
        </span>

        <div class="cf-editor-preview-toolbar-end">
            <span class="cf-editor-step-toolbar-actions">
                <template x-if="toolbarPath === 'welcome'">
                    <button type="button" role="switch" x-on:click="toggleWelcome()" x-bind:aria-checked="isWelcomeShown ? 'true' : 'false'" class="cf-editor-switch">
                        <span class="cf-editor-switch-track" aria-hidden="true"></span>
                        {{ $text('show_welcome') }}
                    </button>
                </template>

                <template x-if="selectedGroupPath && toolbarPath === selectedGroupPath">
                    <button
                        type="button"
                        x-on:click="openPicker(`${selectedGroupPath}.questions`)"
                        x-bind:disabled="isBusy"
                        x-tooltip="{ content: @js($text('add_question')), theme: $store.theme }"
                        aria-label="{{ $text('add_question') }}"
                        class="cf-editor-toolbar-button"
                    >
                        <x-filament::icon icon="heroicon-m-plus" />
                    </button>
                </template>

                <template x-if="toolbarPath?.startsWith('steps.')">
                    <button
                        type="button"
                        x-on:click="setView('logic')"
                        x-tooltip="{ content: @js($text('logic_button')), theme: $store.theme }"
                        aria-label="{{ $text('logic_button') }}"
                        class="cf-editor-toolbar-button"
                    >
                        <x-filament::icon icon="heroicon-m-arrow-turn-down-right" />
                        <span x-show="hasLogicAt(toolbarPath)" class="cf-editor-toolbar-dot" aria-hidden="true"></span>
                    </button>
                </template>

                <template x-if="toolbarPath && toolbarPath !== 'welcome'">
                    <button
                        type="button"
                        x-on:click="isPanelOpen ? closePanel() : openPanel()"
                        x-bind:aria-expanded="isPanelOpen ? 'true' : 'false'"
                        x-tooltip="{ content: @js($text('settings_button')), theme: $store.theme }"
                        aria-label="{{ $text('settings_button') }}"
                        data-cf-editor-opens-panel
                        class="cf-editor-toolbar-button"
                    >
                        <x-filament::icon icon="heroicon-m-cog-6-tooth" />
                    </button>
                </template>
            </span>
        </div>
    </div>

    <template x-if="contrastIssues.length">
        <div role="status" class="cf-editor-contrast">
            <p class="cf-editor-contrast-title">{{ __('conversational-forms::conversational-forms.themes.contrast_title') }}</p>
            <ul>
                <template x-for="issue in contrastIssues" x-bind:key="issue">
                    <li x-text="issue"></li>
                </template>
            </ul>
        </div>
    </template>

    <div x-bind:class="{ 'cf-editor-stage-mobile': device === 'mobile' }" class="cf-editor-stage">
        {{-- Drawn with the conversation's own classes and variables, so it looks like the public page. --}}
        <div
            x-bind:style="themeStyle"
            x-bind:data-cf-theme="themeMode"
            class="cf-conversation cf-editor-conversation"
        >
            <template x-if="fontUrl">
                <link rel="stylesheet" x-bind:href="fontUrl" />
            </template>

            <template x-if="settings.progress_bar !== false && preview.kind === 'step'">
                <div class="cf-progress">
                    <div class="cf-progress-bar" x-bind:style="{ width: `${progress * 100}%` }"></div>
                </div>
            </template>

            <template x-if="preview.kind === 'empty'">
                <div class="cf-screen">
                    <h2 class="cf-screen-title">{{ $text('empty_heading') }}</h2>
                    <p class="cf-screen-description">{{ $text('empty_description') }}</p>
                </div>
            </template>

            <template x-if="preview.kind === 'welcome'">
                <div class="cf-screen cf-welcome-screen">
                    <h2 class="cf-screen-title">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'preview.path', 'field' => 'title', 'label' => $field('title'), 'placeholder' => $placeholder('title')])</h2>
                    <div class="cf-screen-description">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'preview.path', 'field' => 'description', 'label' => $field('description'), 'placeholder' => $placeholder('description'), 'multiline' => true])</div>
                    <div class="cf-step-actions">
                        <span class="cf-button">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'preview.path', 'field' => 'button_label', 'label' => $field('button_label'), 'placeholder' => __('conversational-forms::conversational-forms.welcome_screen.start'), 'inline' => true])</span>
                        <span class="cf-hint cf-hint-enter" aria-hidden="true">{!! __('conversational-forms::conversational-forms.navigation.press_enter') !!}</span>
                    </div>
                </div>
            </template>

            <template x-if="preview.kind === 'ending'">
                <div class="cf-screen cf-end-screen">
                    <template x-if="preview.item.type === 'redirect'">
                        <div class="cf-editor-redirect">
                            <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="cf-editor-redirect-icon" />
                            <p x-text="preview.item.url ? @js($text('redirects_to')).replace(':url', preview.item.url) : @js($text('no_redirect'))"></p>
                        </div>
                    </template>
                    <template x-if="preview.item.type !== 'redirect'">
                        <div class="cf-editor-screen-content">
                            <h2 class="cf-screen-title">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'preview.path', 'field' => 'title', 'label' => $field('title'), 'placeholder' => __('conversational-forms::conversational-forms.end_screen.title')])</h2>
                            <div class="cf-screen-description">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'preview.path', 'field' => 'description', 'label' => $field('description'), 'placeholder' => $placeholder('description'), 'multiline' => true])</div>
                            <div class="cf-step-actions">
                                <span x-bind:class="{ 'cf-editor-button-unused': ! preview.item.button_url }" class="cf-button">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'preview.path', 'field' => 'button_label', 'label' => $field('end_button_label'), 'placeholder' => $placeholder('end_button'), 'inline' => true])</span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="preview.kind === 'step'">
                <section
                    x-bind:class="{ 'cf-statement': preview.item.type === 'statement', 'cf-question-group': preview.item.type === 'group' }"
                    class="cf-step cf-active"
                >
                    <div class="cf-step-content">
                        {{-- A statement, or the heading of a group. --}}
                        <template x-if="['statement', 'group'].includes(preview.item.type)">
                            <div class="cf-question-header">
                                <template x-if="preview.item.type === 'group' && preview.number">
                                    @include('conversational-forms::builder.editor.previews.number', ['number' => 'preview.number'])
                                </template>
                                <h2 class="cf-question-label">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'preview.path', 'field' => "preview.item.type === 'statement' ? 'title' : 'label'", 'isExpression' => true, 'label' => $field('title'), 'placeholder' => $placeholder('title')])</h2>
                                <div class="cf-question-description">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'preview.path', 'field' => 'description', 'label' => $field('description'), 'placeholder' => $placeholder('description'), 'multiline' => true])</div>
                            </div>
                        </template>

                        <div x-bind:class="{ 'cf-question-group-fields': preview.item.type === 'group' }">
                            <template x-for="question in previewQuestions" x-bind:key="question.path">
                                <div
                                    x-bind:class="{
                                        'cf-question-with-media': image(question.data?.media?.url),
                                        'cf-editor-question-selected': preview.item.type === 'group' && isPreviewSelected(question.path),
                                    }"
                                    class="cf-question"
                                >
                                    <div class="cf-question-header">
                                        <template x-if="preview.item.type !== 'group' && preview.number">
                                            @include('conversational-forms::builder.editor.previews.number', ['number' => 'preview.number'])
                                        </template>

                                        <div class="cf-question-label">
                                            @include('conversational-forms::builder.editor.previews.editable', ['path' => 'question.path', 'field' => 'label', 'label' => $field('question'), 'placeholder' => $placeholder('question')])<template x-if="isRequired(question)"><span class="cf-question-required" aria-hidden="true">*</span></template>
                                        </div>

                                        <div class="cf-question-description">@include('conversational-forms::builder.editor.previews.editable', ['path' => 'question.path', 'field' => 'description', 'label' => $field('description'), 'placeholder' => $placeholder('description'), 'multiline' => true])</div>
                                    </div>

                                    <template x-if="image(question.data?.media?.url)">
                                        <figure class="cf-question-media">
                                            <img x-bind:src="image(question.data.media.url)" x-bind:alt="question.data.media.alt ?? ''" />
                                        </figure>
                                    </template>

                                    <div class="cf-question-control">
                                        @foreach ($previewViews as $type => $view)
                                            <template x-if="question.type === @js($type)">
                                                @include($view)
                                            </template>
                                        @endforeach

                                        <template x-if="! hasPreview(question.type)">
                                            <p class="cf-editor-no-preview">{{ $text('no_preview') }}</p>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="cf-step-actions">
                        <span class="cf-button">
                            <template x-if="preview.item.type === 'statement'">
                                @include('conversational-forms::builder.editor.previews.editable', ['path' => 'preview.path', 'field' => 'button_label', 'label' => $field('button_label'), 'placeholder' => __('conversational-forms::conversational-forms.navigation.continue'), 'inline' => true])
                            </template>
                            <template x-if="preview.item.type !== 'statement'">
                                <span>{{ __('conversational-forms::conversational-forms.navigation.ok') }}</span>
                            </template>
                        </span>
                        <span class="cf-hint cf-hint-enter" aria-hidden="true">{!! __('conversational-forms::conversational-forms.navigation.press_enter') !!}</span>
                    </div>
                </section>
            </template>

            <template x-if="settings.navigation_buttons !== false && preview.kind === 'step'">
                <div class="cf-navigation">
                    <span class="cf-navigation-button">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9.47 6.47a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 1 1-1.06 1.06L10 8.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06l4.25-4.25Z" clip-rule="evenodd" /></svg>
                    </span>
                    <span class="cf-navigation-button">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                    </span>
                </div>
            </template>
        </div>
    </div>
</div>
