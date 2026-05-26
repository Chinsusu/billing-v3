<?php

namespace App\Services\Ops;

class OpsAlertCandidate
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $fingerprint,
        public readonly string $severity,
        public readonly string $title,
        public readonly string $message,
        public readonly array $context,
    ) {}
}
