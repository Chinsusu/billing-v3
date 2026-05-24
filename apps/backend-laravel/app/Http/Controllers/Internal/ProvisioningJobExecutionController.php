<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\ProvisioningJob;
use App\Services\Provisioning\ProvisioningExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ProvisioningJobExecutionController extends Controller
{
    public function __invoke(Request $request, string $job, ProvisioningExecutor $executor): JsonResponse
    {
        $token = (string) config('services.internal_provisioning.token');
        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            abort(403);
        }

        $provisioningJob = ProvisioningJob::query()->whereKey($job)->firstOrFail();

        try {
            return response()->json($executor->execute($provisioningJob)->toArray());
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
