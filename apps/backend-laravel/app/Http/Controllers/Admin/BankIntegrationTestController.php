<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankIntegration;
use App\Services\Finance\PrivateBankClient;
use Illuminate\Http\RedirectResponse;
use Throwable;

class BankIntegrationTestController extends Controller
{
    public function __invoke(BankIntegration $bankIntegration, PrivateBankClient $client): RedirectResponse
    {
        try {
            $client->transactions($bankIntegration);
            $bankIntegration->update([
                'last_tested_at' => now(),
                'last_sync_status' => 'tested',
                'last_sync_error' => null,
            ]);

            return redirect('/admin/bank-integrations')->with('status', 'Bank connection tested.');
        } catch (Throwable $exception) {
            $bankIntegration->update([
                'last_tested_at' => now(),
                'last_sync_status' => 'failed',
                'last_sync_error' => $exception->getMessage(),
            ]);

            return redirect('/admin/bank-integrations')->withErrors(['bank_integration' => $exception->getMessage()]);
        }
    }
}
