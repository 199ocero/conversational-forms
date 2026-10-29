<?php

namespace JaOcero\ConversationalForms\Logic;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The steps a respondent will see given their answers so far, the end screen
 * they will reach, and the variables calculated along the way.
 *
 * @implements Arrayable<int, string>
 */
final readonly class ConversationPath implements Arrayable
{
    /**
     * @param  list<string>  $steps
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public array $steps,
        public ?string $ending = null,
        public array $variables = [],
    ) {}

    public function contains(string $step): bool
    {
        return in_array($step, $this->steps, strict: true);
    }

    public function indexOf(string $step): ?int
    {
        $index = array_search($step, $this->steps, strict: true);

        return is_int($index) ? $index : null;
    }

    public function first(): ?string
    {
        return $this->steps[0] ?? null;
    }

    public function after(string $step): ?string
    {
        $index = $this->indexOf($step);

        return ($index === null) ? null : ($this->steps[$index + 1] ?? null);
    }

    public function isLast(string $step): bool
    {
        return $this->contains($step) && ($this->after($step) === null);
    }

    /**
     * The share of the path completed once `$step` has been answered, from 0 to 1.
     */
    public function progressAfter(string $step): float
    {
        $index = $this->indexOf($step);

        if (($index === null) || ($this->steps === [])) {
            return 0.0;
        }

        return round(($index + 1) / count($this->steps), 4);
    }

    public function count(): int
    {
        return count($this->steps);
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return $this->steps;
    }
}
