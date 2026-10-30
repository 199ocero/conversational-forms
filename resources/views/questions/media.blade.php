@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $id = $getId();
    $statePath = $getStatePath();
    $kind = $getKind();
    $maxDuration = $getMaxDuration();
    $acceptedFileTypes = $getAcceptedFileTypes();
    $allowsUpload = $allowsUpload();
    $translations = __('conversational-forms::conversational-forms.media');
    $device = $translations[$kind === 'video' ? 'camera' : 'microphone'];
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('media-question', ConversationalFormsServiceProvider::getAssetPackageName()) }}"
        x-data="mediaQuestionComponent({
            statePath: @js($statePath),
            kind: @js($kind),
            maxDuration: @js($maxDuration),
            maxSize: @js($getMaxSize()),
            translations: @js(collect($translations['status'])->map(fn (string $text): string => str_replace(':device', $device, $text))->all()),
        })"
        role="group"
        aria-labelledby="{{ $id }}-label"
        x-bind:data-cf-media-status="status"
        @class(['cf-media', "cf-media-{$kind}"])
    >
        <p class="cf-sr-only" aria-live="polite" x-text="message"></p>

        @if ($kind === 'video')
            <video x-ref="live" x-show="status === 'recording'" x-cloak muted playsinline autoplay class="cf-media-live"></video>
        @endif

        {{-- Before recording: one clear button, and what will happen. --}}
        <div x-show="status === 'idle' || status === 'requesting'" class="cf-media-start">
            <button type="button" x-on:click="start()" x-bind:disabled="status === 'requesting'" class="cf-media-record">
                <span class="cf-media-record-dot" aria-hidden="true"></span>
                <span x-show="status === 'idle'">{{ $translations['record'] }}</span>
                <span x-show="status === 'requesting'" x-cloak>{{ str_replace(':device', $device, $translations['status']['requesting']) }}</span>
            </button>
            <p class="cf-hint">{{ str_replace([':device', ':duration'], [$device, gmdate($maxDuration >= 3600 ? 'G:i:s' : 'i:s', $maxDuration)], $translations['hint']) }}</p>
        </div>

        {{-- Recording: time and a stop button. --}}
        <div x-show="status === 'recording'" x-cloak class="cf-media-recording">
            <span class="cf-media-recording-dot" aria-hidden="true"></span>
            <span class="cf-media-timer" x-text="elapsedLabel" aria-hidden="true"></span>
            <button type="button" x-on:click="stop()" class="cf-media-stop">{{ $translations['stop'] }}</button>
        </div>

        {{-- A recording or upload to play back. --}}
        <template x-if="previewUrl && (status === 'uploading' || status === 'done')">
            @if ($kind === 'video')
                <video x-bind:src="previewUrl" controls playsinline x-on:loadedmetadata="measure($event.target)" class="cf-media-playback"></video>
            @else
                <audio x-bind:src="previewUrl" controls x-on:loadedmetadata="measure($event.target)" class="cf-media-playback"></audio>
            @endif
        </template>

        <div x-show="status === 'uploading'" x-cloak class="cf-media-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progress" aria-label="{{ $translations['saving'] }}">
            <span x-bind:style="`width: ${progress}%`"></span>
        </div>

        <div x-show="status === 'done'" x-cloak class="cf-media-done">
            <p class="cf-media-saved">
                <span aria-hidden="true">✓</span>
                <span x-text="fileName"></span>
            </p>
            <button type="button" x-on:click="again()" x-show="! isProblem" class="cf-media-again">{{ $translations['again'] }}</button>
        </div>

        {{-- What went wrong, and the way forward. --}}
        <p x-show="isProblem" x-cloak role="alert" class="cf-media-problem" x-text="message"></p>

        <div x-show="isProblem && status !== 'unsupported' && status !== 'too_large'" x-cloak>
            <button type="button" x-on:click="start()" class="cf-media-again">{{ $translations['try_again'] }}</button>
        </div>

        @if ($allowsUpload)
            <label x-show="status !== 'recording' && status !== 'uploading'" class="cf-media-upload">
                <input
                    type="file"
                    @if (filled($acceptedFileTypes)) accept="{{ implode(',', $acceptedFileTypes) }}" @endif
                    x-on:change="handleSelect($event)"
                    class="cf-sr-only"
                />
                <span>{{ $translations['upload_instead'] }}</span>
            </label>
        @else
            <label x-show="status === 'unsupported'" x-cloak class="cf-media-upload">
                <input
                    type="file"
                    @if (filled($acceptedFileTypes)) accept="{{ implode(',', $acceptedFileTypes) }}" @endif
                    x-on:change="handleSelect($event)"
                    class="cf-sr-only"
                />
                <span>{{ $translations['upload_instead'] }}</span>
            </label>
        @endif
    </div>
</x-conversational-forms::question>
