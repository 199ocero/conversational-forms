@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fi">
    <head>
        <meta charset="utf-8" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

        <title>{{ $title ?? config('app.name') }}</title>

        <style>
            [x-cloak] {
                display: none !important;
            }

            :root {
                /* Read by Filament's dark mode script: follow the operating system. */
                --default-theme-mode: system;
            }
        </style>

        @filamentStyles

        {{-- Filament's compiled stylesheet, so fallback Filament fields are styled without a Vite build. --}}
        {{ FilamentAsset::getTheme('app')?->getHtml() }}

        <script>
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark')
            }
        </script>
    </head>

    <body @class(['fi-body', 'cf-public-body', 'cf-embedded-body' => $isEmbedded ?? false])>
        {{ $slot }}

        @filamentScripts(withCore: true)
            @if ($isEmbedded ?? false)
            {{-- Embedded with the embed script: tell the page around the iframe how tall the conversation is. --}}
            {{-- The page itself is measured, not the document: the body is sized to the viewport, which would grow with the iframe. --}}
            <script>
                const cfEmbeddedPage = document.querySelector('.cf-public-page')

                new ResizeObserver(() => {
                    window.parent?.postMessage(
                        { type: 'conversational-forms:resize', height: Math.ceil(cfEmbeddedPage.getBoundingClientRect().height) },
                        '*',
                    )
                }).observe(cfEmbeddedPage)
            </script>
        @endif
    </body>
</html>
