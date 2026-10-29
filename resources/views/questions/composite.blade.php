@php
    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $description = $getDescription();
@endphp

<x-conversational-forms::question :field="$field" :label-target="false" :shows-nested-errors="false">
    <div role="group" aria-labelledby="{{ $id }}-label" @if (filled($description)) aria-describedby="{{ $id }}-description" @endif class="cf-composite">
        @foreach ($getParts() as $name => $part)
            @php
                $partId = "{$id}-{$name}";
                $partStatePath = "{$statePath}.{$name}";
                $partError = $errors->first($partStatePath);
                $partIsRequired = $isPartRequired($name);
            @endphp

            <div @class(['cf-composite-part', 'cf-composite-part-half' => ($part['width'] ?? 'full') === 'half'])>
                <label for="{{ $partId }}" class="cf-composite-label">
                    {{ $part['label'] }}@if ($partIsRequired)<span class="cf-question-required" aria-hidden="true">*</span>@endif
                </label>

                <input
                    id="{{ $partId }}"
                    type="{{ $part['type'] ?? 'text' }}"
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $partStatePath }}"
                    @if (filled($part['autocomplete'] ?? null)) autocomplete="{{ $part['autocomplete'] }}" @endif
                    @if (filled($part['inputmode'] ?? null)) inputmode="{{ $part['inputmode'] }}" @endif
                    @if ($partIsRequired) aria-required="true" @endif
                    @if (filled($partError)) aria-invalid="true" aria-describedby="{{ $partId }}-error" @endif
                    @if ($loop->first) autofocus @endif
                    @disabled($isDisabled())
                    class="cf-input cf-composite-input"
                />

                @if (filled($partError))
                    <p id="{{ $partId }}-error" role="alert" class="cf-question-error">{{ $partError }}</p>
                @endif
            </div>
        @endforeach
    </div>
</x-conversational-forms::question>
