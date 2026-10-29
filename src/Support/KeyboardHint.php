<?php

namespace JaOcero\ConversationalForms\Support;

final readonly class KeyboardHint
{
    public function __construct(
        public string $keys,
        public string $description,
    ) {}

    public static function make(string $keys, string $description): self
    {
        return new self($keys, $description);
    }
}
