<?php

namespace App\Http\Requests;

use App\Models\ProvisioningProviderAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProvisioningProviderAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('provisioning_provider_accounts.manage') ?? false;
    }

    public function rules(): array
    {
        /** @var ProvisioningProviderAccount|null $account */
        $account = $this->route('provisioningProviderAccount');

        return [
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('provisioning_provider_accounts', 'slug')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:160'],
            'driver' => ['required', Rule::in(['sandbox', 'generic_http'])],
            'base_url' => ['nullable', 'required_if:driver,generic_http', 'url', 'max:255'],
            'provision_path' => ['nullable', 'required_if:driver,generic_http', 'string', 'max:255', 'starts_with:/'],
            'auth_type' => ['required', Rule::in(['none', 'bearer', 'header'])],
            'auth_header_name' => ['nullable', 'required_if:auth_type,header', 'string', 'max:80'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['nullable', 'boolean'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:120'],
            'request_template' => ['nullable', 'json'],
            'response_external_id_path' => ['required', 'string', 'max:120'],
            'response_status_path' => ['required', 'string', 'max:120'],
            'response_config_path' => ['nullable', 'string', 'max:120'],
        ];
    }
}
