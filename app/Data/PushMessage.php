<?php

namespace App\Data;

use App\Enums\NotificationCategory;

final readonly class PushMessage
{
    /** @param array<string, scalar|null> $data */
    public function __construct(
        public string $title,
        public string $body,
        public NotificationCategory $category,
        public string $deepLink,
        public array $data = [],
    ) {}

    /** @return array<string, string> */
    public function dataPayload(): array
    {
        return collect([
            'category' => $this->category->value,
            'deep_link' => $this->deepLink,
            ...$this->data,
        ])->map(fn (mixed $value): string => (string) $value)->all();
    }
}
