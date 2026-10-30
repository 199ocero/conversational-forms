@php
    use Filament\Support\Facades\FilamentAsset;
    use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;

    $package = ConversationalFormsServiceProvider::getAssetPackageName();
    $map = $this->getLogicMap();
    $labels = collect($map->nodes)->mapWithKeys(fn (array $node): array => [$node['id'] => $node['key']]);
@endphp

<x-filament-panels::page>
    @if (filled($map->warnings))
        <div role="alert" class="cf-logic-warnings">
            <p class="cf-logic-warnings-title">{{ __('conversational-forms::conversational-forms.builder.logic.warnings') }}</p>
            <ul>
                @foreach ($map->warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('logic-map', $package) }}"
        x-load-css="[@js(FilamentAsset::getStyleHref('conversational-forms', $package))]"
        x-data="logicMapComponent({ edges: @js($map->edges) })"
        class="cf-logic-map"
    >
        <ol class="cf-logic-nodes">
            @foreach ($map->nodes as $node)
                @php
                    $outgoing = collect($map->edges)->where('from', $node['id'])->reject(fn (array $edge): bool => $edge['kind'] === 'next');
                    $next = collect($map->edges)->firstWhere(fn (array $edge): bool => ($edge['from'] === $node['id']) && ($edge['kind'] === 'next'));
                @endphp

                @if ($loop->first || (($node['kind'] === 'ending') && ($map->nodes[$loop->index - 1]['kind'] ?? null) === 'step'))
                    <li class="cf-logic-heading" aria-hidden="true">
                        {{ __('conversational-forms::conversational-forms.builder.logic.' . (($node['kind'] === 'ending') ? 'endings' : 'steps')) }}
                    </li>
                @endif

                <li
                    data-cf-node="{{ $node['id'] }}"
                    x-bind:class="{ 'cf-active': isActive(@js($node['id'])) }"
                    @class([
                        'cf-logic-node',
                        'cf-logic-ending' => $node['kind'] === 'ending',
                        'cf-logic-unreachable' => ! $node['reachable'],
                    ])
                >
                    <div class="cf-logic-node-header">
                        @if ($node['number'] !== null)
                            <span class="cf-logic-number">{{ $node['number'] }}</span>
                        @endif

                        <span class="cf-logic-type">{{ $node['type'] }}</span>
                        <code class="cf-logic-key">{{ $node['key'] }}</code>

                        @if ($node['conditional'])
                            <span class="cf-logic-badge">{{ __('conversational-forms::conversational-forms.builder.logic.conditional') }}</span>
                        @endif

                        @if (! $node['reachable'])
                            <span class="cf-logic-badge cf-logic-badge-warning">{{ __('conversational-forms::conversational-forms.builder.logic.unreachable') }}</span>
                        @endif
                    </div>

                    <p class="cf-logic-label">{{ $node['label'] }}</p>

                    @if ($outgoing->isNotEmpty() || $next)
                        <ul class="cf-logic-rules">
                            @foreach ($outgoing as $index => $edge)
                                <li
                                    x-on:mouseenter="active = @js($edge)"
                                    x-on:mouseleave="active = null"
                                    @class(['cf-logic-rule', 'cf-logic-rule-end' => $edge['kind'] === 'end'])
                                >
                                    <span class="cf-logic-rule-when">{{ $edge['label'] }}</span>
                                    <span aria-hidden="true">→</span>
                                    <code>{{ $labels[$edge['to']] ?? $edge['to'] }}</code>
                                </li>
                            @endforeach

                            @if ($next)
                                <li class="cf-logic-rule cf-logic-rule-next">
                                    <span class="cf-logic-rule-when">{{ __('conversational-forms::conversational-forms.builder.logic.' . ($outgoing->isEmpty() ? 'then' : 'otherwise')) }}</span>
                                    <span aria-hidden="true">→</span>
                                    <code>{{ $labels[$next['to']] ?? $next['to'] }}</code>
                                </li>
                            @endif
                        </ul>
                    @endif
                </li>
            @endforeach
        </ol>

        <svg class="cf-logic-arcs" aria-hidden="true" x-bind:viewBox="`0 0 ${width} ${height}`" x-bind:width="width" x-bind:height="height">
            <defs>
                <marker id="cf-logic-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="context-stroke" />
                </marker>
            </defs>

            {{-- Drawn from JavaScript: Alpine's templates don't work inside SVG. --}}
            <g x-html="markup()"></g>
        </svg>
    </div>
</x-filament-panels::page>
