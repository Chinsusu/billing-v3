<?php

namespace App\Services\Provisioning;

use App\Models\ProvisioningJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProvisioningJobRetryService
{
    public function retry(ProvisioningJob $job): void
    {
        if ($job->status !== 'failed') {
            throw ValidationException::withMessages([
                'provisioning_job' => 'Only failed provisioning jobs can be retried.',
            ]);
        }

        DB::transaction(function () use ($job): void {
            $job->forceFill([
                'status' => 'pending',
                'available_at' => now(),
                'processed_at' => null,
                'last_error' => null,
            ])->save();
        });
    }
}
