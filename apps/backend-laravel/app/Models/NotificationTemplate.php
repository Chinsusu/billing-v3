<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'channel', 'name', 'subject_template', 'body_template', 'variables', 'enabled'])]
class NotificationTemplate extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
