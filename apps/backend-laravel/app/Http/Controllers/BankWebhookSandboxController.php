<?php

namespace App\Http\Controllers;

use App\Services\Finance\BankWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankWebhookSandboxController extends Controller
{
    public function __invoke(Request $request, BankWebhookProcessor $processor): JsonResponse
    {
        $result = $processor->handle($request->getContent(), $request->header('X-Billing-Signature'));

        return response()->json($result['body'], $result['status_code']);
    }
}
