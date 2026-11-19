<?php

declare(strict_types=1);

namespace App\DTOs\Receipts;

use Carbon\CarbonImmutable;

final readonly class ReceiptLinkResult
{
    public function __construct(
        public string $url,
        public CarbonImmutable $expiresAt,
    ) {}

    /**
     * @return array{url: string, expires_at: string}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }
}
