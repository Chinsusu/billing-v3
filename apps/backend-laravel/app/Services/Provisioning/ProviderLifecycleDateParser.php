<?php

namespace App\Services\Provisioning;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ProviderLifecycleDateParser
{
    public function parse(mixed $value, string $format, string $timezone): Carbon
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException('date value is missing');
        }

        return match ($format) {
            'unix_seconds' => $this->fromUnixSeconds($value),
            'unix_ms' => $this->fromUnixMilliseconds($value),
            default => Carbon::parse((string) $value, $timezone),
        };
    }

    private function fromUnixSeconds(mixed $value): Carbon
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('unix seconds date value must be numeric');
        }

        return Carbon::createFromTimestampUTC((int) $value);
    }

    private function fromUnixMilliseconds(mixed $value): Carbon
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('unix milliseconds date value must be numeric');
        }

        return Carbon::createFromTimestampUTC(((int) $value) / 1000);
    }
}
