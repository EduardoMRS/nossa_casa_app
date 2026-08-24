<?php

namespace App\Data;

use JsonSerializable;

final readonly class CanonicalData implements JsonSerializable
{
    /** @param array<string, mixed> $attributes */
    public function __construct(private array $attributes) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
