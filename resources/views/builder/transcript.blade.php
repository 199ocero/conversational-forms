<div class="fi-sc-text">
    <dl style="display: grid; gap: 1.25rem">
        @forelse ($rows as $row)
            <div>
                <dt style="font-size: 0.875rem; opacity: 0.7">{{ $row['question'] }}</dt>
                <dd style="margin: 0.25rem 0 0; font-weight: 500; white-space: pre-line">{{ filled($row['answer']) ? $row['answer'] : '—' }}</dd>
            </div>
        @empty
            <p>{{ __('conversational-forms::conversational-forms.responses.no_answers') }}</p>
        @endforelse

        @if (filled($response->variables))
            <div>
                <dt style="font-size: 0.875rem; opacity: 0.7">{{ __('conversational-forms::conversational-forms.responses.variables') }}</dt>
                <dd style="margin: 0.25rem 0 0; font-weight: 500">
                    @foreach ($response->variables as $name => $value)
                        <code>{{ '@' . $name }}</code> = {{ is_scalar($value) ? $value : json_encode($value) }}@if (! $loop->last), @endif
                    @endforeach
                </dd>
            </div>
        @endif
    </dl>
</div>
