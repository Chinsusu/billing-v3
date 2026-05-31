<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProvisioningProviderAccountRequest;
use App\Http\Requests\UpdateProvisioningProviderAccountRequest;
use App\Models\ProvisioningProviderAccount;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProvisioningProviderAccountController extends Controller
{
    private const AUDIT_FIELDS = ['slug', 'name', 'driver', 'base_url', 'provision_path', 'auth_type', 'auth_header_name', 'api_key', 'api_key_last_four', 'callback_secret', 'callback_secret_last_four', 'enabled', 'timeout_seconds', 'request_template', 'response_external_id_path', 'response_status_path', 'response_config_path', 'callback_event_id_path', 'callback_external_id_path', 'callback_action_path', 'callback_status_path'];

    public function index(): View
    {
        return view('admin.provisioning-provider-accounts.index', [
            'providerAccounts' => ProvisioningProviderAccount::query()
                ->orderBy('driver')
                ->orderBy('name')
                ->get(),
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

    public function store(StoreProvisioningProviderAccountRequest $request, AuditLogger $audit): RedirectResponse
    {
        $providerAccount = ProvisioningProviderAccount::create($this->attributesForSave($request->validated(), null, $request->user()));
        $audit->record(
            $request->user(),
            'created',
            $providerAccount,
            [],
            $audit->snapshot($providerAccount, self::AUDIT_FIELDS),
            [],
            $request,
        );

        return redirect('/admin/provisioning-provider-accounts')->with('status', 'Provider account created.');
    }

    public function edit(ProvisioningProviderAccount $provisioningProviderAccount): View
    {
        return view('admin.provisioning-provider-accounts.edit', [
            'providerAccount' => $provisioningProviderAccount,
        ]);
    }

    public function update(UpdateProvisioningProviderAccountRequest $request, ProvisioningProviderAccount $provisioningProviderAccount, AuditLogger $audit): RedirectResponse
    {
        $before = $audit->snapshot($provisioningProviderAccount, self::AUDIT_FIELDS);
        $provisioningProviderAccount->update($this->attributesForSave($request->validated(), $provisioningProviderAccount, $request->user()));
        $provisioningProviderAccount->refresh();
        [$beforeChanges, $afterChanges] = $audit->diff($before, $audit->snapshot($provisioningProviderAccount, self::AUDIT_FIELDS));
        $audit->record($request->user(), 'updated', $provisioningProviderAccount, $beforeChanges, $afterChanges, [], $request);

        return redirect('/admin/provisioning-provider-accounts')->with('status', 'Provider account updated.');
    }

    private function attributesForSave(array $attributes, ?ProvisioningProviderAccount $existing, User $actor): array
    {
        $apiKey = (string) ($attributes['api_key'] ?? '');
        $callbackSecret = (string) ($attributes['callback_secret'] ?? '');
        $requestTemplate = (string) ($attributes['request_template'] ?? '');

        $usesCloudmini = $attributes['driver'] === 'cloudmini_v3';

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
            'response_external_id_path' => $attributes['response_external_id_path'] ?? ($usesCloudmini ? 'resource_snapshot.id' : 'external_id'),
            'response_status_path' => $attributes['response_status_path'] ?? ($usesCloudmini ? 'state' : 'status'),
            'response_config_path' => $attributes['response_config_path'] ?? ($usesCloudmini ? 'resource_snapshot' : null),
            'callback_event_id_path' => $attributes['callback_event_id_path'] ?? 'event_id',
            'callback_external_id_path' => $attributes['callback_external_id_path'] ?? 'external_id',
            'callback_action_path' => $attributes['callback_action_path'] ?? 'action',
            'callback_status_path' => $attributes['callback_status_path'] ?? 'status',
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
