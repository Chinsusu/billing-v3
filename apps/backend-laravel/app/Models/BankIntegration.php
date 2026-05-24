<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'name', 'base_url', 'transactions_path', 'account_number', 'enabled', 'api_key', 'webhook_secret', 'api_key_last_four', 'webhook_secret_last_four', 'last_tested_at', 'last_sync_at', 'last_sync_status', 'last_sync_error', 'created_by_id', 'updated_by_id'])]
class BankIntegration extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'api_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'last_tested_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function transactionsUrl(): string
    {
        return rtrim($this->base_url, '/').'/'.ltrim($this->transactions_path, '/');
    }
}
