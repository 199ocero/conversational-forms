<?php

namespace JaOcero\ConversationalForms\Logic;

use Illuminate\Contracts\Support\Arrayable;
use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JsonSerializable;

/**
 * Conditions combined with "all" (AND) or "any" (OR). Groups can be nested.
 *
 * @implements Arrayable<string, mixed>
 */
final class ConditionGroup implements Arrayable, JsonSerializable
{
    public const ALL = 'all';

    public const ANY = 'any';

    /**
     * @param  list<Condition | ConditionGroup>  $items
     */
    public function __construct(
        private string $combinator = self::ALL,
        private array $items = [],
    ) {
        if (! in_array($combinator, [self::ALL, self::ANY], strict: true)) {
            throw new InvalidLogicException("A condition group must be [all] or [any]; [{$combinator}] was given.");
        }
    }

    /**
     * @param  array<string, mixed>  $data  `['all' => [...]]` or `['any' => [...]]`
     */
    public static function fromArray(array $data): self
    {
        $combinator = array_key_first($data);

        return new self((string) $combinator, array_map(
            fn (array $item): Condition | ConditionGroup => isset($item['field']) ? Condition::fromArray($item) : self::fromArray($item),
            array_values($data[$combinator] ?? []),
        ));
    }

    public function add(Condition | ConditionGroup $item, string $combinator): void
    {
        if ((count($this->items) > 1) && ($this->combinator !== $combinator)) {
            throw new InvalidLogicException('A rule cannot mix [and()] and [or()]. Group the conditions with [Logic::all()] or [Logic::any()] instead.');
        }

        $this->combinator = $combinator;
        $this->items[] = $item;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $variables
     */
    public function matches(array $answers, array $variables = []): bool
    {
        if ($this->combinator === self::ANY) {
            foreach ($this->items as $item) {
                if ($item->matches($answers, $variables)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($this->items as $item) {
            if (! $item->matches($answers, $variables)) {
                return false;
            }
        }

        return true;
    }

    public function referencesVariables(): bool
    {
        foreach ($this->items as $item) {
            if (($item instanceof Condition) ? $item->isVariable() : $item->referencesVariables()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string> The answers (not variables) these conditions read.
     */
    public function getFields(): array
    {
        $fields = [];

        foreach ($this->items as $item) {
            if ($item instanceof Condition) {
                if (! $item->isVariable()) {
                    $fields[] = $item->field;
                }

                continue;
            }

            $fields = [...$fields, ...$item->getFields()];
        }

        return array_values(array_unique($fields));
    }

    public function getCombinator(): string
    {
        return $this->combinator;
    }

    /**
     * @return list<Condition | ConditionGroup>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function toArray(): array
    {
        return [$this->combinator => array_map(fn (Condition | ConditionGroup $item): array => $item->toArray(), $this->items)];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
