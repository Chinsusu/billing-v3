<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProvisioningProviderAccountRequest;
use App\Http\Requests\UpdateProvisioningProviderAccountRequest;
use App\Models\ProvisioningProviderAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProvisioningProviderAccountController extends Controller
{
    public function index(): View
    {
        return view('admin.provisioning-provider-accounts.index', [
            'providerAccounts' => ProvisioningProviderAccount::latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.provisioning-provider-accounts.create', [
            'providerAccount' => new ProvisioningProviderAccount([
                'driver' => 'sandbox',
                'auth_type' => 'none',
                'enabled' => true,
                'timeout_seconds' => 15,
                'request_template' => [],
                'response_external_id_path' => 'external_id',
                'response_status_path' => 'status',
                'callback_event_id_path' => 'event_id',
                'callback_external_id_path' => 'external_id',
                'callback_action_path' => 'action',
                'callback_status_path' => 'status',
            ]),
        ]);
    }

    public function store(StoreProvisioningProviderAccountRequest $request): RedirectResponse
    {
        ProvisioningProviderAccount::create($this->attributesForSave($request->validated(), null, $request->user()));

        return redirect('/admin/provisioning-provider-accounts')->with('status', 'Provider account created.');
    }

    public function edit(ProvisioningProviderAccount $provisioningProviderAccount): View
    {
        return view('admin.provisioning-provider-accounts.edit', [
            'providerAccount' => $provisioningProviderAccount,
        ]);
    }

    public function update(UpdateProvisioningProviderAccountRequest $request, ProvisioningProviderAccount $provisioningProviderAccount): RedirectResponse
    {
        $provisioningProviderAccount->update($this->attributesForSave($request->validated(), $provisioningProviderAccount, $request->user()));

        return redirect('/admin/provisioning-provider-accounts')->with('status', 'Provider account updated.');
    }

    private function attributesForSave(array $attributes, ?ProvisioningProviderAccount $existing, User $actor): array
    {
        $apiKey = (string) ($attributes['api_key'] ?? '');
        $callbackSecret = (string) ($attributes['callback_secret'] ?? '');
        $requestTemplate = (string) ($attributes['request_template'] ?? '');

        $data = [
            'slug' => $attributes['slug'],
            'name' => $attributes['name'],
            'driver' => $attributes['driver'],
            'base_url' => isset($attributes['base_url']) && $attributes['base_url'] !== '' ? rtrim($attributes['base_url'], '/') : null,
            'provision_path' => isset($attributes['provision_path']) && $attributes['provision_path'] !== '' ? '/'.ltrim($attributes['provision_path'], '/') : null,
            'auth_type' => $attributes['auth_type'],
            'auth_header_name' => isset($attributes['auth_header_name']) && $attributes['auth_header_name'] !== '' ? $attributes['auth_header_name'] : null,
            'enabled' => (bool) ($attributes['enabled'] ?? false),
            'timeout_seconds' => (int) $attributes['timeout_seconds'],
            'request_template' => $requestTemplate !== '' ? json_decode($requestTemplate, true) : [],
            'response_external_id_path' => $attributes['response_external_id_path'],
            'response_status_path' => $attributes['response_status_path'],
            'response_config_path' => $attributes['response_config_path'] ?? null,
            'callback_event_id_path' => $attributes['callback_event_id_path'],
            'callback_external_id_path' => $attributes['callback_external_id_path'],
            'callback_action_path' => $attributes['callback_action_path'],
            'callback_status_path' => $attributes['callback_status_path'],
            'updated_by_id' => $actor->id,
        ];

        if ($existing === null) {
            $data['created_by_id'] = $actor->id;
        }

        if ($existing === null || $apiKey !== '') {
            $data['api_key'] = $apiKey !== '' ? $apiKey : null;
            $data['api_key_last_four'] = $apiKey !== '' ? substr($apiKey, -4) : null;
        }

        if ($existing === null || $callbackSecret !== '') {
            $data['callback_secret'] = $callbackSecret !== '' ? $callbackSecret : null;
            $data['callback_secret_last_four'] = $callbackSecret !== '' ? substr($callbackSecret, -4) : null;
        }

        return $data;
    }
}
