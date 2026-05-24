<?php

namespace App\Services\Provisioning;

class JsonPath
{
    public function get(array $payload, ?string $path): mixed
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $value = $payload;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
