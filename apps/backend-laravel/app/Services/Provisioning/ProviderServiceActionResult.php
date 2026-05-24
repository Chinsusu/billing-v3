<?php

namespace App\Services\Provisioning;

use Illuminate\Support\Carbon;

class ProviderServiceActionResult
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?Carbon $expiresAt = null,
        public readonly array $response = [],
    ) {}
}
