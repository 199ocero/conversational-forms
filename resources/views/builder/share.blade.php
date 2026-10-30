@php
    $snippets = [
        'link' => $url,
        'iframe' => '<iframe src="' . $url . '?embed=1" title="' . e($title) . '" style="width:100%;height:600px;border:0" loading="lazy"></iframe>',
        'script' => '<div data-conversational-form="' . $slug . '"></div>' . "\n" . '<script src="' . $scriptUrl . '" async></script>',
    ];
@endphp

<div class="fi-sc-text" style="display: grid; gap: 1.5rem">
    @foreach ($snippets as $kind => $snippet)
        <div x-data="{ copied: false }" style="display: grid; gap: 0.5rem">
            <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 1rem">
                <p style="margin: 0; font-weight: 600; font-size: 0.875rem">{{ __("conversational-forms::conversational-forms.share.{$kind}") }}</p>
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText(@js($snippet)).then(() => { copied = true; setTimeout(() => (copied = false), 2000) })"
                    style="font-size: 0.875rem; font-weight: 500; color: var(--primary-600)"
                >
                    <span x-show="! copied">{{ __('conversational-forms::conversational-forms.share.copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('conversational-forms::conversational-forms.share.copied') }}</span>
                </button>
            </div>
            <p style="margin: 0; font-size: 0.8125rem; opacity: 0.7">{{ __("conversational-forms::conversational-forms.share.{$kind}_help") }}</p>
            <pre style="margin: 0; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.8125rem; white-space: pre-wrap; word-break: break-all; background: color-mix(in oklab, currentColor 6%, transparent)"><code>{{ $snippet }}</code></pre>
        </div>
    @endforeach

    @if ($qrCode)
        <div style="display: grid; gap: 0.5rem; justify-items: start">
            <p style="margin: 0; font-weight: 600; font-size: 0.875rem">{{ __('conversational-forms::conversational-forms.share.qr_code') }}</p>
            <div style="padding: 0.75rem; background: white; border-radius: 0.5rem; line-height: 0">{!! $qrCode !!}</div>
            <a href="data:image/svg+xml;base64,{{ base64_encode($qrCode) }}" download="{{ $slug }}-qr.svg" style="font-size: 0.875rem; font-weight: 500; color: var(--primary-600)">
                {{ __('conversational-forms::conversational-forms.share.download_qr') }}
            </a>
        </div>
    @endif
</div>
