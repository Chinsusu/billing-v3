<?php

namespace App\Services\Provisioning;

final readonly class ProvisioningResult
{
    public function __construct(
        public string $status,
        public string $externalId,
        public array $config = [],
        public mixed $orderedAt = null,
        public mixed $expiresAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'external_id' => $this->externalId,
            'config' => $this->config,
            'ordered_at' => $this->orderedAt?->toAtomString(),
            'expires_at' => $this->expiresAt?->toAtomString(),
        ];
    }
}
