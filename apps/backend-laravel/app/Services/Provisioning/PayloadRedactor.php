<?php

namespace App\Services\Provisioning;

class PayloadRedactor
{
    /** @var list<string> */
    private const SENSITIVE_KEY_PARTS = [
        'authorization',
        'api_key',
        'key',
        'token',
        'secret',
        'password',
    ];

    public function redact(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return $this->redactArray($payload);
    }

    private function redactArray(array $payload): array
    {
        $redacted = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = '***redacted***';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redactArray($value) : $value;
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }
}
