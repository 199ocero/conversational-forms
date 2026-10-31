<?php

namespace JaOcero\ConversationalForms\Builder;

use Illuminate\Support\Str;
use JaOcero\ConversationalForms\Definitions\QuestionTypeRegistry;
use JaOcero\ConversationalForms\Logic\Enums\AnswerType;
use JaOcero\ConversationalForms\Logic\Enums\Operator;

/**
 * Converts between a stored definition and the builder's form state.
 *
 * The builder edits rules as rows ("when [field] [operator] [value], [jump
 * to] [step]"). Rules that the rows can't express, such as nested groups of
 * conditions written in code, are kept as they are and shown read-only.
 */
final class DefinitionState
{
    /**
     * Settings stored as numbers; the builder's inputs give strings.
     */
    private const NUMERIC_SETTINGS = ['min_length', 'max_length', 'rows', 'min', 'max', 'min_selections', 'max_selections', 'steps', 'start', 'end', 'max_files', 'max_size', 'first_day_of_week', 'max_duration'];

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function fromDefinition(array $definition): array
    {
        $settings = $definition['settings'] ?? [];
        $welcome = $definition['welcome'] ?? null;

        return [
            'steps' => self::keyed(array_map(self::stepToState(...), $definition['steps'] ?? [])),
            'welcome' => [
                'enabled' => filled($welcome),
                'title' => $welcome['title'] ?? null,
                'description' => $welcome['description'] ?? null,
                'button_label' => $welcome['button_label'] ?? null,
            ],
            'endings' => self::keyed(array_map(fn (array $ending): array => [
                'key' => $ending['key'] ?? 'default',
                'type' => $ending['type'] ?? 'end_screen',
                'title' => $ending['title'] ?? null,
                'description' => $ending['description'] ?? null,
                'button_label' => $ending['button_label'] ?? null,
                'button_url' => $ending['button_url'] ?? null,
                'url' => $ending['url'] ?? null,
            ], ($definition['endings'] ?? []) ?: [['key' => 'default']])),
            'variables' => array_map(fn (mixed $value): string => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, $definition['variables'] ?? []),
            'hidden_fields' => self::keyed(array_map(fn (array $field): array => [
                'key' => $field['key'],
                'from_query' => $field['from_query'] ?? null,
                'max_length' => $field['max_length'] ?? null,
            ], $definition['hidden_fields'] ?? [])),
            'settings' => [
                'progress_bar' => $settings['progress_bar'] ?? true,
                'navigation_buttons' => $settings['navigation_buttons'] ?? true,
                'auto_advance' => $settings['auto_advance'] ?? true,
                'auto_advance_delay' => $settings['auto_advance_delay'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function toDefinition(array $state): array
    {
        $answerTypes = self::answerTypes($state['steps'] ?? []);
        $settings = $state['settings'] ?? [];
        $welcome = $state['welcome'] ?? [];

        return self::withoutBlanks([
            'schema_version' => 1,
            'settings' => self::withoutBlanks([
                'progress_bar' => (bool) ($settings['progress_bar'] ?? true),
                'navigation_buttons' => (bool) ($settings['navigation_buttons'] ?? true),
                'auto_advance' => (bool) ($settings['auto_advance'] ?? true),
                'auto_advance_delay' => self::number($settings['auto_advance_delay'] ?? null),
            ]),
            'variables' => array_map(self::scalar(...), $state['variables'] ?? []) ?: null,
            'welcome' => ($welcome['enabled'] ?? false) ? self::withoutBlanks([
                'title' => $welcome['title'] ?? null,
                'description' => $welcome['description'] ?? null,
                'button_label' => $welcome['button_label'] ?? null,
            ]) ?: ['title' => null] : null,
            'hidden_fields' => array_values(array_map(fn (array $field): array => self::withoutBlanks([
                'key' => $field['key'] ?? null,
                'from_query' => $field['from_query'] ?? null,
                'max_length' => self::number($field['max_length'] ?? null),
            ]), $state['hidden_fields'] ?? [])) ?: null,
            'steps' => array_values(array_map(fn (array $item): array => self::stateToStep($item, $answerTypes), $state['steps'] ?? [])),
            'endings' => array_values(array_map(fn (array $ending): array => self::withoutBlanks([
                'key' => $ending['key'] ?? 'default',
                'type' => $ending['type'] ?? 'end_screen',
                'title' => $ending['title'] ?? null,
                'description' => $ending['description'] ?? null,
                'button_label' => $ending['button_label'] ?? null,
                'button_url' => $ending['button_url'] ?? null,
                'url' => (($ending['type'] ?? null) === 'redirect') ? ($ending['url'] ?? null) : null,
            ]), $state['endings'] ?? [])),
        ], keep: ['steps', 'endings']);
    }

    /**
     * A key made from a label: "What's your name?" becomes `whats_your_name`.
     */
    public static function keyFromLabel(?string $label): string
    {
        $key = (string) Str::of(strip_tags((string) $label))->replaceMatches('/\{[^}]*\}/', '')->ascii()->snake()->replaceMatches('/[^a-z0-9_]/', '')->trim('_')->limit(40, '');

        return (preg_match('/^[a-z]/', $key) === 1) ? rtrim($key, '_') : 'question' . ($key === '' ? '' : "_{$key}");
    }

    /**
     * Every key used by the builder's steps, including questions in groups.
     *
     * @param  array<array-key, mixed>  $steps
     * @return list<string>
     */
    public static function stepKeys(array $steps): array
    {
        $keys = [];

        foreach ($steps as $item) {
            $keys[] = $item['data']['key'] ?? null;

            foreach ($item['data']['questions'] ?? [] as $question) {
                $keys[] = $question['data']['key'] ?? null;
            }
        }

        return array_values(array_unique(array_filter($keys, filled(...))));
    }

    /**
     * A copy of a builder item (a step, or a question in a group) whose keys
     * don't clash with `$takenKeys`: `email` becomes `email_2`, then `email_3`.
     *
     * @param  array{type?: string, data?: array<string, mixed>}  $item
     * @param  list<string>  $takenKeys
     * @return array{type?: string, data?: array<string, mixed>}
     */
    public static function copyItem(array $item, array $takenKeys): array
    {
        $copyKey = function (?string $key) use (&$takenKeys): ?string {
            if (blank($key)) {
                return $key;
            }

            $base = preg_replace('/_\d+$/', '', $key) ?: $key;
            $number = 2;

            while (in_array("{$base}_{$number}", $takenKeys, strict: true)) {
                $number++;
            }

            return $takenKeys[] = "{$base}_{$number}";
        };

        if (array_key_exists('key', $item['data'] ?? [])) {
            $item['data']['key'] = $copyKey($item['data']['key']);
        }

        if (is_array($item['data']['questions'] ?? null)) {
            $questions = [];

            foreach ($item['data']['questions'] as $question) {
                if (array_key_exists('key', $question['data'] ?? [])) {
                    $question['data']['key'] = $copyKey($question['data']['key']);
                }

                $questions[(string) Str::uuid()] = $question;
            }

            $item['data']['questions'] = $questions;
        }

        return $item;
    }

    /**
     * The answer type of every question in the builder, keyed by question key.
     *
     * @param  array<array-key, mixed>  $steps
     * @return array<string, AnswerType>
     */
    public static function answerTypes(array $steps): array
    {
        $registry = app(QuestionTypeRegistry::class);
        $types = [];

        foreach (self::questionItems($steps) as $item) {
            $key = $item['data']['key'] ?? null;

            if (filled($key) && $registry->has($item['type'])) {
                $types[$key] = $registry->get($item['type'])->getAnswerType($item['data']['settings'] ?? []);
            }
        }

        return $types;
    }

    /**
     * Every question in the builder, including those inside groups, as
     * `['type' => ..., 'data' => [...]]` items.
     *
     * @param  array<array-key, mixed>  $steps
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public static function questionItems(array $steps): array
    {
        $items = [];

        foreach ($steps as $item) {
            $type = $item['type'] ?? null;

            if ($type === 'group') {
                foreach ($item['data']['questions'] ?? [] as $question) {
                    $items[] = ['type' => (string) ($question['type'] ?? ''), 'data' => $question['data'] ?? []];
                }
            } elseif (($type !== null) && ($type !== 'statement')) {
                $items[] = ['type' => (string) $type, 'data' => $item['data'] ?? []];
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array{type: string, data: array<string, mixed>}
     */
    private static function stepToState(array $step): array
    {
        $type = (string) ($step['type'] ?? '');
        $data = match ($type) {
            'statement' => [
                'key' => $step['key'] ?? null,
                'title' => $step['title'] ?? null,
                'description' => $step['description'] ?? null,
                'button_label' => $step['button_label'] ?? null,
            ],
            'group' => [
                'key' => $step['key'] ?? null,
                'label' => $step['label'] ?? null,
                'description' => $step['description'] ?? null,
                'questions' => self::keyed(array_map(self::stepToState(...), $step['questions'] ?? [])),
            ],
            default => self::questionToState($step),
        };

        $data['visibility'] = self::conditionsToState($step['visible_when'] ?? null);
        $data['logic'] = self::keyed(array_map(self::ruleToState(...), $step['rules'] ?? []));

        return ['type' => $type, 'data' => $data];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private static function questionToState(array $question): array
    {
        $settings = $question['settings'] ?? [];

        foreach (['options', 'rows', 'answers'] as $list) {
            if (isset($settings[$list]) && is_array($settings[$list])) {
                $settings[$list] = self::keyed($settings[$list]);
            }
        }

        if (array_key_exists('twelve_hour', $settings)) {
            $settings['twelve_hour'] = ($settings['twelve_hour'] === null) ? null : (int) $settings['twelve_hour'];
        }

        return [
            'key' => $question['key'] ?? null,
            'label' => $question['label'] ?? null,
            'description' => $question['description'] ?? null,
            'required' => (bool) ($question['required'] ?? false),
            'media' => ['url' => $question['media']['url'] ?? null, 'alt' => $question['media']['alt'] ?? null],
            'settings' => $settings,
            'correct' => [
                'enabled' => isset($question['correct']),
                'answer' => isset($question['correct']) ? self::valueToState($question['correct']['answer'] ?? null) : null,
                'points' => $question['correct']['points'] ?? null,
                'variable' => $question['correct']['variable'] ?? null,
            ],
        ];
    }

    /**
     * @param  array{type?: string, data?: array<string, mixed>}  $item
     * @param  array<string, AnswerType>  $answerTypes
     * @return array<string, mixed>
     */
    private static function stateToStep(array $item, array $answerTypes): array
    {
        $type = (string) ($item['type'] ?? '');
        $data = $item['data'] ?? [];

        $step = match ($type) {
            'statement' => [
                'type' => 'statement',
                'key' => $data['key'] ?? null,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'button_label' => $data['button_label'] ?? null,
            ],
            'group' => [
                'type' => 'group',
                'key' => $data['key'] ?? null,
                'label' => $data['label'] ?? null,
                'description' => $data['description'] ?? null,
                'questions' => array_values(array_map(fn (array $question): array => self::stateToStep($question, $answerTypes), $data['questions'] ?? [])),
            ],
            default => self::stateToQuestion($type, $data, $answerTypes),
        };

        $step['visible_when'] = self::stateToConditions($data['visibility'] ?? null, $answerTypes);

        if ($type !== '' && array_key_exists('logic', $data)) {
            $step['rules'] = array_values(array_filter(array_map(
                fn (array $rule): ?array => self::stateToRule($rule, $answerTypes),
                array_values($data['logic'] ?? []),
            ))) ?: null;
        }

        return self::withoutBlanks($step, keep: ['questions']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, AnswerType>  $answerTypes
     * @return array<string, mixed>
     */
    private static function stateToQuestion(string $type, array $data, array $answerTypes): array
    {
        $settings = [];

        foreach ($data['settings'] ?? [] as $name => $value) {
            $settings[$name] = match (true) {
                in_array($name, ['options', 'rows', 'answers'], strict: true) && is_array($value) => self::optionsFromState($value),
                in_array($name, self::NUMERIC_SETTINGS, strict: true) => self::number($value),
                $name === 'twelve_hour' => (($value === null) || ($value === '')) ? null : (bool) $value,
                is_array($value) => array_values($value),
                default => $value,
            };
        }

        $correct = $data['correct'] ?? [];
        $key = (string) ($data['key'] ?? '');

        return [
            'type' => $type,
            'key' => $key,
            'label' => $data['label'] ?? null,
            'description' => $data['description'] ?? null,
            'required' => ($data['required'] ?? false) ? true : null,
            'media' => filled($data['media']['url'] ?? null) ? ['url' => (string) $data['media']['url'], 'alt' => (string) ($data['media']['alt'] ?? '')] : null,
            'correct' => ($correct['enabled'] ?? false) ? [
                'answer' => self::stateToValue($correct['answer'] ?? null, $answerTypes[$key] ?? AnswerType::Text),
                'points' => self::number($correct['points'] ?? null) ?? 1,
                'variable' => filled($correct['variable'] ?? null) ? (string) $correct['variable'] : 'score',
            ] : null,
            'settings' => self::withoutBlanks($settings) ?: null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $options
     * @return list<array<string, mixed>>
     */
    private static function optionsFromState(array $options): array
    {
        $list = [];

        foreach ($options as $option) {
            $label = trim((string) ($option['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $value = trim((string) ($option['value'] ?? ''));
            $image = $option['image'] ?? null;

            $list[] = self::withoutBlanks([
                'value' => ($value === '') ? Str::slug($label, '_') : $value,
                'label' => $label,
                'image' => is_array($image) ? (array_values($image)[0] ?? null) : $image,
            ]);
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private static function ruleToState(array $rule): array
    {
        $if = $rule['if'] ?? 'always';
        $match = is_array($if) ? (string) array_key_first($if) : 'always';
        $conditions = is_array($if) ? ($if[$match] ?? []) : [];
        $then = $rule['then'] ?? [];

        $isEditable = self::isFlat($conditions);

        $action = 'none';
        $target = null;
        $ending = null;
        $calculations = [];

        foreach ($then as $step) {
            if (isset($step['jump_to'])) {
                [$action, $target] = ['jump_to', $step['jump_to']];
            } elseif (isset($step['end_with'])) {
                [$action, $ending] = ['end_with', $step['end_with']];
            } elseif (isset($step['end'])) {
                $action = 'end';
            } else {
                $operation = (string) array_key_first($step);
                [$variable, $value] = array_pad(array_values((array) $step[$operation]), 2, null);
                $calculations[] = ['operation' => $operation, 'variable' => $variable, 'value' => self::valueToState($value)];
            }
        }

        return [
            'match' => $match,
            'conditions' => $isEditable ? self::keyed(array_map(self::conditionToState(...), $conditions)) : [],
            'action' => $action,
            'target' => $target,
            'ending' => $ending,
            'calculations' => self::keyed($calculations),
            'raw' => $isEditable ? null : json_encode($rule, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, AnswerType>  $answerTypes
     * @return array<string, mixed>|null
     */
    private static function stateToRule(array $state, array $answerTypes): ?array
    {
        if (filled($state['raw'] ?? null)) {
            return json_decode((string) $state['raw'], associative: true);
        }

        $then = array_values(array_filter(array_map(
            fn (array $calculation): ?array => filled($calculation['variable'] ?? null)
                ? [(string) ($calculation['operation'] ?? 'add') => [ltrim((string) $calculation['variable'], '@'), self::scalar($calculation['value'] ?? null)]]
                : null,
            array_values($state['calculations'] ?? []),
        )));

        $then = match ($state['action'] ?? 'none') {
            'jump_to' => filled($state['target'] ?? null) ? [...$then, ['jump_to' => (string) $state['target']]] : $then,
            'end_with' => [...$then, filled($state['ending'] ?? null) ? ['end_with' => (string) $state['ending']] : ['end' => true]],
            'end' => [...$then, ['end' => true]],
            default => $then,
        };

        if ($then === []) {
            return null;
        }

        $match = $state['match'] ?? 'always';
        $conditions = ($match === 'always') ? null : self::stateToConditions(['match' => $match, 'conditions' => $state['conditions'] ?? []], $answerTypes);

        return ['if' => $conditions ?? 'always', 'then' => $then];
    }

    /**
     * @param  array<string, mixed>|null  $group
     * @return array<string, mixed>
     */
    private static function conditionsToState(?array $group): array
    {
        if (blank($group)) {
            return ['match' => 'all', 'conditions' => [], 'raw' => null];
        }

        $match = (string) array_key_first($group);
        $conditions = $group[$match] ?? [];
        $isEditable = self::isFlat($conditions);

        return [
            'match' => $match,
            'conditions' => $isEditable ? self::keyed(array_map(self::conditionToState(...), $conditions)) : [],
            'raw' => $isEditable ? null : json_encode($group, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $state
     * @param  array<string, AnswerType>  $answerTypes
     * @return array<string, list<array<string, mixed>>>|null
     */
    private static function stateToConditions(?array $state, array $answerTypes): ?array
    {
        if (filled($state['raw'] ?? null)) {
            return json_decode((string) $state['raw'], associative: true);
        }

        $conditions = [];

        foreach ($state['conditions'] ?? [] as $condition) {
            $field = (string) ($condition['field'] ?? '');
            $operator = Operator::tryFrom((string) ($condition['operator'] ?? ''));

            if (($field === '') || ($operator === null)) {
                continue;
            }

            $type = str_starts_with($field, '@') ? AnswerType::Number : ($answerTypes[strtok($field, '.')] ?? AnswerType::Text);

            $conditions[] = ['field' => $field, 'op' => $operator->value, ...match (true) {
                ! $operator->needsValue() => [],
                $operator->takesList() => ['value' => array_values(array_map(fn (mixed $value): mixed => self::stateToValue($value, $type), (array) ($condition['values'] ?? [])))],
                default => ['value' => self::stateToValue($condition['value'] ?? null, $type)],
            }];
        }

        return ($conditions === []) ? null : [(($state['match'] ?? 'all') === 'any') ? 'any' : 'all' => $conditions];
    }

    /**
     * @param  array<string, mixed>  $condition
     * @return array<string, mixed>
     */
    private static function conditionToState(array $condition): array
    {
        $value = $condition['value'] ?? null;

        return [
            'field' => $condition['field'],
            'operator' => $condition['op'] ?? 'is',
            'value' => is_array($value) ? null : self::valueToState($value),
            'values' => is_array($value) ? array_map(self::valueToState(...), array_values($value)) : [],
        ];
    }

    /**
     * Whether a list of conditions has no nested groups, so rows can edit it.
     *
     * @param  array<array-key, mixed>  $conditions
     */
    private static function isFlat(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if ((! is_array($condition)) || (! isset($condition['field']))) {
                return false;
            }
        }

        return true;
    }

    private static function valueToState(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    private static function stateToValue(mixed $value, AnswerType $type): mixed
    {
        if (($value === null) || ($value === '')) {
            return null;
        }

        return match ($type) {
            AnswerType::Boolean => in_array($value, [true, 1, '1', 'true', 'yes'], strict: true),
            AnswerType::Number => self::number($value) ?? $value,
            default => (string) $value,
        };
    }

    private static function number(mixed $value): int | float | null
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (str_contains((string) $value, '.') || str_contains(strtolower((string) $value), 'e')) ? (float) $value : (int) $value;
    }

    private static function scalar(mixed $value): int | float | string | bool | null
    {
        return match (true) {
            is_numeric($value) => self::number($value),
            $value === 'true' => true,
            $value === 'false' => false,
            ($value === null) || ($value === '') => null,
            default => (string) $value,
        };
    }

    /**
     * Give list items stable keys, as the builder's repeaters expect.
     *
     * @param  array<array-key, mixed>  $items
     * @return array<string, mixed>
     */
    private static function keyed(array $items): array
    {
        $keyed = [];

        foreach (array_values($items) as $item) {
            $keyed[(string) Str::uuid()] = $item;
        }

        return $keyed;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $keep  Keys kept even when empty.
     * @return array<string, mixed>
     */
    private static function withoutBlanks(array $values, array $keep = []): array
    {
        return array_filter(
            $values,
            fn (mixed $value, string $key): bool => in_array($key, $keep, strict: true) || (! in_array($value, [null, '', []], true)),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
