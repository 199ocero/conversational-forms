<?php

namespace JaOcero\ConversationalForms\Questions\Concerns;

use Closure;

trait HasSegmentDropdowns
{
    protected bool | Closure $hasDropdowns = true;

    /**
     * Show a list of choices under each box (default), so a part of the
     * answer can be picked as well as typed.
     */
    public function dropdowns(bool | Closure $condition = true): static
    {
        $this->hasDropdowns = $condition;

        return $this;
    }

    public function hasDropdowns(): bool
    {
        return (bool) $this->evaluate($this->hasDropdowns);
    }

    /**
     * @param  iterable<int>  $numbers
     * @param  (Closure(int): string)|null  $label
     * @return list<array{value: string, label: string}>
     */
    protected static function segmentOptions(iterable $numbers, int $length, ?Closure $label = null): array
    {
        $options = [];

        foreach ($numbers as $number) {
            $options[] = [
                'value' => str_pad((string) $number, $length, '0', STR_PAD_LEFT),
                'label' => $label ? $label($number) : (string) $number,
            ];
        }

        return $options;
    }
}
