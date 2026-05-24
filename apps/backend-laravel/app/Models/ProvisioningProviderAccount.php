<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'driver', 'base_url', 'provision_path', 'auth_type', 'auth_header_name', 'api_key', 'api_key_last_four', 'enabled', 'timeout_seconds', 'request_template', 'response_external_id_path', 'response_status_path', 'response_config_path', 'last_tested_at', 'last_test_status', 'last_test_error', 'created_by_id', 'updated_by_id'])]
class ProvisioningProviderAccount extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'timeout_seconds' => 'integer',
            'request_template' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'provider_account_id');
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(ProvisioningExecutionLog::class, 'provider_account_id');
    }

    public function providerActionJobs(): HasMany
    {
        return $this->hasMany(ProviderActionJob::class, 'provider_account_id');
    }

    public function endpointUrl(?string $overridePath = null): ?string
    {
        if ($this->base_url === null) {
            return null;
        }

        $path = $overridePath ?: $this->provision_path ?: '';

        return rtrim($this->base_url, '/').($path === '' ? '' : '/'.ltrim($path, '/'));
    }
}
