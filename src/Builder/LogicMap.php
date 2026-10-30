<?php

namespace JaOcero\ConversationalForms\Builder;

use JaOcero\ConversationalForms\Definitions\QuestionTypeRegistry;
use JaOcero\ConversationalForms\Logic\Enums\Operator;

/**
 * The graph of a definition for the builder's logic map: steps and endings,
 * the default flow from each step to the next, the jumps and endings chosen
 * by rules, and warnings about steps or endings that can never be reached.
 *
 * Logic only jumps forward, so the graph is drawn as a list in step order
 * with the rules as arcs beside it.
 *
 * @phpstan-type Node array{id: string, key: string, kind: 'step'|'ending', type: string, label: string, number: int|null, conditional: bool, reachable: bool}
 * @phpstan-type Edge array{from: string, to: string, kind: 'next'|'jump'|'end', label: string}
 */
final readonly class LogicMap
{
    /**
     * @param  list<Node>  $nodes
     * @param  list<Edge>  $edges
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $nodes,
        public array $edges,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromDefinition(array $definition): self
    {
        $registry = app(QuestionTypeRegistry::class);
        $steps = array_values($definition['steps'] ?? []);
        $endings = array_values($definition['endings'] ?? []) ?: [['key' => 'default']];
        $defaultEnding = 'ending:' . ($endings[0]['key'] ?? 'default');

        $nodes = [];
        $edges = [];
        $number = 0;

        foreach ($steps as $index => $step) {
            $type = (string) ($step['type'] ?? '');
            $isQuestion = $type !== 'statement';

            $nodes[] = [
                'id' => "step:{$step['key']}",
                'key' => (string) $step['key'],
                'kind' => 'step',
                'type' => match (true) {
                    $type === 'statement', $type === 'group' => __("conversational-forms::conversational-forms.builder.types.{$type}"),
                    $registry->has($type) => $registry->get($type)->getLabel(),
                    default => $type,
                },
                'label' => strip_tags((string) ($step['label'] ?? $step['title'] ?? $step['key'])),
                'number' => $isQuestion ? ++$number : null,
                'conditional' => filled($step['visible_when'] ?? null),
                'reachable' => false,
            ];

            $continuesByDefault = true;

            foreach ($step['rules'] ?? [] as $rule) {
                $label = self::describeConditions($rule['if'] ?? 'always');

                foreach ($rule['then'] ?? [] as $action) {
                    $target = match (true) {
                        isset($action['jump_to']) => ['step:' . $action['jump_to'], 'jump'],
                        isset($action['end_with']) => ['ending:' . $action['end_with'], 'end'],
                        isset($action['end']) => [$defaultEnding, 'end'],
                        default => null,
                    };

                    if ($target === null) {
                        continue;
                    }

                    $edges[] = ['from' => "step:{$step['key']}", 'to' => $target[0], 'kind' => $target[1], 'label' => $label];

                    // A rule without conditions always routes, so nothing after it runs.
                    if (($rule['if'] ?? 'always') === 'always') {
                        $continuesByDefault = false;

                        break 2;
                    }
                }
            }

            if ($continuesByDefault) {
                $next = isset($steps[$index + 1]) ? "step:{$steps[$index + 1]['key']}" : $defaultEnding;
                $edges[] = ['from' => "step:{$step['key']}", 'to' => $next, 'kind' => 'next', 'label' => ''];
            }
        }

        foreach ($endings as $ending) {
            $nodes[] = [
                'id' => 'ending:' . ($ending['key'] ?? 'default'),
                'key' => (string) ($ending['key'] ?? 'default'),
                'kind' => 'ending',
                'type' => (($ending['type'] ?? 'end_screen') === 'redirect')
                    ? __('conversational-forms::conversational-forms.builder.form.redirect')
                    : __('conversational-forms::conversational-forms.builder.form.end_screen'),
                'label' => strip_tags((string) ($ending['title'] ?? $ending['url'] ?? $ending['key'] ?? 'default')),
                'number' => null,
                'conditional' => false,
                'reachable' => false,
            ];
        }

        if ($steps === []) {
            $edges = [];
        }

        $reachable = self::reachable($steps === [] ? $defaultEnding : "step:{$steps[0]['key']}", $edges);
        $warnings = [];

        foreach ($nodes as $position => $node) {
            $nodes[$position]['reachable'] = isset($reachable[$node['id']]);

            if (! $nodes[$position]['reachable'] && ($steps !== [])) {
                $warnings[] = __("conversational-forms::conversational-forms.builder.logic.unreachable_{$node['kind']}", ['key' => $node['key']]);
            }
        }

        return new self($nodes, $edges, $warnings);
    }

    /**
     * @param  'always'|array<string, mixed>  $conditions
     */
    public static function describeConditions(string | array $conditions): string
    {
        if (! is_array($conditions)) {
            return __('conversational-forms::conversational-forms.builder.form.summary_always');
        }

        $combinator = (string) array_key_first($conditions);
        $parts = array_map(function (mixed $item): string {
            if (! is_array($item)) {
                return '';
            }

            if (! isset($item['field'])) {
                return '(' . self::describeConditions($item) . ')';
            }

            $operator = Operator::tryFrom((string) ($item['op'] ?? ''));
            $value = $item['value'] ?? null;
            $value = match (true) {
                ($operator !== null) && (! $operator->needsValue()) => '',
                is_array($value) => ' ' . implode(', ', array_map(strval(...), $value)),
                is_bool($value) => ' ' . __('conversational-forms::conversational-forms.builder.form.' . ($value ? 'yes' : 'no')),
                default => ' ' . $value,
            };

            return "{$item['field']} " . ($operator?->getLabel() ?? (string) ($item['op'] ?? '')) . $value;
        }, array_values($conditions[$combinator] ?? []));

        $joiner = ' ' . __('conversational-forms::conversational-forms.builder.form.' . (($combinator === 'any') ? 'summary_or' : 'summary_and')) . ' ';

        return implode($joiner, array_filter($parts));
    }

    /**
     * @param  list<Edge>  $edges
     * @return array<string, true>
     */
    private static function reachable(string $start, array $edges): array
    {
        $seen = [$start => true];
        $queue = [$start];

        while ($queue !== []) {
            $node = array_shift($queue);

            foreach ($edges as $edge) {
                if (($edge['from'] === $node) && (! isset($seen[$edge['to']]))) {
                    $seen[$edge['to']] = true;
                    $queue[] = $edge['to'];
                }
            }
        }

        return $seen;
    }
}
