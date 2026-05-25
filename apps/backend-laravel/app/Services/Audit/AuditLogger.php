<?php

namespace App\Services\Audit;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?User $actor,
        string $action,
        Model $auditable,
        array $before = [],
        array $after = [],
        array $metadata = [],
        ?Request $request = null,
        ?string $label = null,
    ): AdminAuditLog {
        $request ??= request();
        $route = $request->route();

        return AdminAuditLog::create([
            'actor_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => (string) $auditable->getKey(),
            'auditable_label' => $label ?? $this->labelFor($auditable),
            'route_name' => $route?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'before' => $this->sanitize($before),
            'after' => $this->sanitize($after),
            'metadata' => $this->sanitize($metadata),
        ]);
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    public function snapshot(Model $model, array $fields): array
    {
        $snapshot = [];

        foreach ($fields as $field) {
            $snapshot[$field] = $this->normalizeValue($model->{$field});
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function diff(array $before, array $after): array
    {
        $beforeChanges = [];
        $afterChanges = [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($keys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;

            if ($beforeValue !== $afterValue) {
                $beforeChanges[$key] = $beforeValue;
                $afterChanges[$key] = $afterValue;
            }
        }

        return [$beforeChanges, $afterChanges];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitize(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $sanitized[$key] = $value === null || $value === '' ? null : '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
                continue;
            }

            $sanitized[$key] = $this->normalizeValue($value);
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        if (str_ends_with($lower, 'last_four')) {
            return false;
        }

        return preg_match('/(password|secret|token|api[_-]?key|private[_-]?key|authorization|bearer)/', $lower) === 1;
    }

    private function labelFor(Model $model): ?string
    {
        foreach (['name', 'code', 'email', 'slug', 'type', 'action'] as $attribute) {
            $value = $model->{$attribute} ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $model->getKey() === null ? null : (string) $model->getKey();
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return $value;
    }
}
