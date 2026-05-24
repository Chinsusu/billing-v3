<?php

namespace App\Services\Provisioning;

final readonly class ProvisioningResult
{
    public function __construct(
        public string $status,
        public string $externalId,
        public array $config = [],
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'external_id' => $this->externalId,
            'config' => $this->config,
        ];
    }
}
