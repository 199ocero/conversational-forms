@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $hasError = $errors->has($statePath) || $errors->has("{$statePath}.*");
    $description = $getDescription();
    $isMultiple = $isMultiple();
    $acceptedFileTypes = $getAcceptedFileTypes();
    $maxSize = $getMaxSize();

    $hint = collect([
        filled($acceptedFileTypes) ? implode(', ', array_unique(array_map(fn (string $type): string => match (true) {
            str_ends_with($type, '/*') => __('conversational-forms::conversational-forms.upload.types.' . str($type)->before('/')->toString()),
            str_starts_with($type, '.') => str($type)->after('.')->upper()->toString(),
            default => str($type)->afterLast('/')->afterLast('.')->upper()->toString(),
        }, $acceptedFileTypes))) : null,
        filled($maxSize) ? __('conversational-forms::conversational-forms.upload.max_size', ['size' => $maxSize >= 1024 ? round($maxSize / 1024, 1) . ' MB' : "{$maxSize} KB"]) : null,
    ])->filter()->implode(' · ');
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('file-upload-question', ConversationalFormsServiceProvider::getAssetPackageName()) }}"
        x-data="fileUploadQuestionComponent({
            statePath: @js($statePath),
            isMultiple: @js($isMultiple),
            maxFiles: @js($getMaxFiles()),
            maxSize: @js($maxSize),
            acceptedFileTypes: @js($acceptedFileTypes),
            translations: @js(__('conversational-forms::conversational-forms.upload')),
        })"
        class="cf-upload"
    >
        <label
            for="{{ $id }}"
            x-on:dragover.prevent="isDragging = true"
            x-on:dragleave.prevent="isDragging = false"
            x-on:drop.prevent="handleDrop($event)"
            x-bind:class="{ 'cf-dragging': isDragging }"
            class="cf-dropzone"
        >
            <input
                id="{{ $id }}"
                type="file"
                @if ($isMultiple) multiple @endif
                @if (filled($acceptedFileTypes)) accept="{{ implode(',', $acceptedFileTypes) }}" @endif
                x-on:change="handleSelect($event)"
                aria-labelledby="{{ $id }}-label {{ $id }}-cta"
                @if (filled($description) || filled($hint) || $hasError)
                    aria-describedby="{{ collect([filled($description) ? "{$id}-description" : null, filled($hint) ? "{$id}-hint" : null, $hasError ? "{$id}-error" : null])->filter()->implode(' ') }}"
                @endif
                @if ($hasError) aria-invalid="true" @endif
                @if ($isRequired()) aria-required="true" @endif
                @disabled($isDisabled())
                class="cf-dropzone-input"
            />

            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="cf-dropzone-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>

            <span id="{{ $id }}-cta" class="cf-dropzone-text">
                {{ $isMultiple ? __('conversational-forms::conversational-forms.upload.choose_files') : __('conversational-forms::conversational-forms.upload.choose_file') }}
            </span>

            @if (filled($hint))
                <span id="{{ $id }}-hint" class="cf-hint">{{ $hint }}</span>
            @endif
        </label>

        <ul class="cf-upload-files" x-show="files.length" x-cloak>
            <template x-for="file in files" x-bind:key="file.key">
                <li x-bind:class="{ 'cf-upload-error': file.status === 'error' }" class="cf-upload-file">
                    <span class="cf-upload-file-name" x-text="file.name"></span>
                    <span class="cf-upload-file-size" x-text="file.size"></span>

                    <span class="cf-upload-progress" x-show="file.status === 'uploading'" role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="file.progress" x-bind:aria-label="file.name">
                        <span class="cf-upload-progress-bar" x-bind:style="{ width: `${file.progress}%` }"></span>
                    </span>

                    <button type="button" x-on:click="remove(file)" x-bind:aria-label="@js(__('conversational-forms::conversational-forms.upload.remove')).replace(':name', file.name)" class="cf-upload-remove">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                    </button>
                </li>
            </template>
        </ul>

        <p class="cf-sr-only" aria-live="polite" x-text="message"></p>
        <p role="alert" x-show="problem" x-text="problem" x-cloak class="cf-question-error"></p>
    </div>
</x-conversational-forms::question>
