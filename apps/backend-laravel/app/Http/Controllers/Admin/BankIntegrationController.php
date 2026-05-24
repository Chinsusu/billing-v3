<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankIntegration;
use App\Services\Finance\BankIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BankIntegrationController extends Controller
{
    public function index(): View
    {
        return view('admin.bank-integrations.index', [
            'bankIntegrations' => BankIntegration::latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.bank-integrations.create', [
            'bankIntegration' => new BankIntegration([
                'provider' => 'private_bank',
                'transactions_path' => '/api/transactions',
                'enabled' => true,
            ]),
        ]);
    }

    public function store(Request $request, BankIntegrationService $service): RedirectResponse
    {
        $service->create($this->validated($request), $request->user());

        return redirect('/admin/bank-integrations')->with('status', 'Bank integration created.');
    }

    public function edit(BankIntegration $bankIntegration): View
    {
        return view('admin.bank-integrations.edit', ['bankIntegration' => $bankIntegration]);
    }

    public function update(Request $request, BankIntegration $bankIntegration, BankIntegrationService $service): RedirectResponse
    {
        $service->update($bankIntegration, $this->validated($request, $bankIntegration), $request->user());

        return redirect('/admin/bank-integrations')->with('status', 'Bank integration updated.');
    }

    private function validated(Request $request, ?BankIntegration $bankIntegration = null): array
    {
        return $request->validate([
            'provider' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('bank_integrations', 'provider')->ignore($bankIntegration?->id)],
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:255'],
            'transactions_path' => ['required', 'string', 'max:255', 'starts_with:/'],
            'account_number' => ['nullable', 'string', 'max:120'],
            'enabled' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'webhook_secret' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
