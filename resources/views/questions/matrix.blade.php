@php
    $errors = $getLivewire()->getErrorBag();
    $id = $getId();
    $statePath = $getStatePath();
    $description = $getDescription();
    $rows = $getRows();
    $columns = $getAnswerOptions();
    $isMultiple = $isMultiple();
    $isRequired = $isRequired();
    $wireModel = $applyStateBindingModifiers('wire:model');
@endphp

<x-conversational-forms::question :field="$field" :label-target="false">
    <div role="group" aria-labelledby="{{ $id }}-label" @if (filled($description)) aria-describedby="{{ $id }}-description" @endif class="cf-matrix" style="--cf-matrix-columns: {{ count($columns) }}">
        <div class="cf-matrix-header" aria-hidden="true">
            <span></span>

            @foreach ($columns as $columnLabel)
                <span class="cf-matrix-column-label">{{ $columnLabel }}</span>
            @endforeach
        </div>

        @foreach ($rows as $row => $rowLabel)
            @php($rowHasError = $errors->has("{$statePath}.{$row}") || $errors->has("{$statePath}.{$row}.*"))

            <fieldset
                @if ((! $isMultiple) && $isRequired) aria-required="true" @endif
                @if ($rowHasError) aria-invalid="true" @endif
                @class(['cf-matrix-row', 'cf-matrix-row-invalid' => $rowHasError])
            >
                <legend class="cf-matrix-row-label">{{ $rowLabel }}</legend>

                @foreach ($columns as $column => $columnLabel)
                    <label class="cf-matrix-cell">
                        <input
                            type="{{ $isMultiple ? 'checkbox' : 'radio' }}"
                            name="{{ $id }}-{{ $row }}"
                            value="{{ $column }}"
                            {{ $wireModel }}="{{ $statePath }}.{{ $row }}"
                            @if ($loop->parent->first && $loop->first) autofocus @endif
                            @disabled($isDisabled())
                            class="cf-matrix-input"
                        />

                        <span class="cf-matrix-cell-label">{{ $columnLabel }}</span>
                    </label>
                @endforeach
            </fieldset>
        @endforeach
    </div>
</x-conversational-forms::question>
