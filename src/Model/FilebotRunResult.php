<?php

namespace App\Model;

final readonly class FilebotRunResult
{
    private function __construct(
        public string $status,
        public ?string $reason = null,
    ) {
    }

    public static function completed(): self
    {
        return new self('completed');
    }

    public static function skipped(string $reason): self
    {
        return new self('skipped', $reason);
    }
}
