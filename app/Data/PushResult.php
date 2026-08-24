<?php

namespace App\Data;

final readonly class PushResult
{
    public function __construct(
        public bool $sent,
        public bool $invalidToken = false,
        public ?string $providerMessageId = null,
        public ?string $error = null,
    ) {}

    public static function sent(?string $providerMessageId = null): self
    {
        return new self(true, providerMessageId: $providerMessageId);
    }

    public static function disabled(): self
    {
        return new self(false, error: 'PUSH_DISABLED');
    }

    public static function failed(string $error, bool $invalidToken = false): self
    {
        return new self(false, $invalidToken, error: $error);
    }
}
