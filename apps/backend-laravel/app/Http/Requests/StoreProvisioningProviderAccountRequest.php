<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProvisioningProviderAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('provisioning_provider_accounts.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->missingCallbackDefaults([
            'callback_event_id_path' => 'event_id',
            'callback_external_id_path' => 'external_id',
            'callback_action_path' => 'action',
            'callback_status_path' => 'status',
        ]));
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:provisioning_provider_accounts,slug'],
            'name' => ['required', 'string', 'max:160'],
            'driver' => ['required', Rule::in(['sandbox', 'generic_http', 'cloudmini_v3'])],
            'base_url' => ['nullable', Rule::requiredIf(fn (): bool => in_array($this->input('driver'), ['generic_http', 'cloudmini_v3'], true)), 'url', 'max:255'],
            'provision_path' => ['nullable', 'required_if:driver,generic_http', 'string', 'max:255', 'starts_with:/'],
            'auth_type' => ['required', Rule::in(['none', 'bearer', 'header'])],
            'auth_header_name' => ['nullable', 'required_if:auth_type,header', 'string', 'max:80'],
            'api_key' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('driver') === 'cloudmini_v3'), 'string', 'max:2000'],
            'enabled' => ['nullable', 'boolean'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:120'],
            'request_template' => ['nullable', 'json'],
            'response_external_id_path' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('driver') !== 'cloudmini_v3'), 'string', 'max:120'],
            'response_status_path' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('driver') !== 'cloudmini_v3'), 'string', 'max:120'],
            'response_config_path' => ['nullable', 'string', 'max:120'],
            'callback_secret' => ['nullable', 'string', 'max:2000'],
            'callback_event_id_path' => ['required', 'string', 'max:120'],
            'callback_external_id_path' => ['required', 'string', 'max:120'],
            'callback_action_path' => ['required', 'string', 'max:120'],
            'callback_status_path' => ['required', 'string', 'max:120'],
        ];
    }

    private function missingCallbackDefaults(array $defaults): array
    {
        return array_filter(
            $defaults,
            fn (string $key): bool => ! $this->has($key),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
