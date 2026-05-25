<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'lifecycle_source' => $this->input('lifecycle_source', 'local_policy'),
            'lifecycle_unit' => $this->input('lifecycle_unit', 'day'),
            'lifecycle_count' => $this->input('lifecycle_count', $this->input('duration_days', 30)),
            'provider_lifecycle_date_format' => $this->input('provider_lifecycle_date_format', 'iso8601'),
            'provider_lifecycle_timezone' => $this->input('provider_lifecycle_timezone', 'UTC'),
            'auto_renew_allowed' => $this->has('auto_renew_allowed') ? $this->boolean('auto_renew_allowed') : true,
            'auto_renew_window_hours' => $this->input('auto_renew_window_hours', 24),
            'auto_renew_retry_delay_minutes' => $this->input('auto_renew_retry_delay_minutes', 60),
            'auto_renew_max_attempts' => $this->input('auto_renew_max_attempts', 3),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'code' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('products', 'code')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['proxy', 'vps'])],
            'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
            'price_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'auto_renew_allowed' => ['required', 'boolean'],
            'auto_renew_window_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'auto_renew_retry_delay_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'auto_renew_max_attempts' => ['required', 'integer', 'min:1', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'provider_account_id' => ['nullable', 'uuid', 'exists:provisioning_provider_accounts,id'],
            'provider_plan_code' => ['nullable', 'string', 'max:120'],
            'provider_region' => ['nullable', 'string', 'max:80'],
            'provider_provision_path' => ['nullable', 'string', 'max:255', 'starts_with:/'],
            'provider_options' => ['nullable', 'json'],
            'lifecycle_source' => ['required', Rule::in(['local_policy', 'provider_response', 'provider_lookup'])],
            'lifecycle_unit' => ['required', Rule::in(['day', 'calendar_month'])],
            'lifecycle_count' => ['required', 'integer', 'min:1', 'max:3650'],
            'provider_lifecycle_path' => ['nullable', 'required_if:lifecycle_source,provider_lookup', 'string', 'max:255', 'starts_with:/'],
            'provider_lifecycle_ordered_at_path' => ['nullable', 'required_if:lifecycle_source,provider_response,provider_lookup', 'string', 'max:160'],
            'provider_lifecycle_expires_at_path' => ['nullable', 'required_if:lifecycle_source,provider_response,provider_lookup', 'string', 'max:160'],
            'provider_lifecycle_date_format' => ['required', Rule::in(['iso8601', 'unix_seconds', 'unix_ms'])],
            'provider_lifecycle_timezone' => ['required', 'timezone'],
            'provider_renew_path' => ['nullable', 'string', 'max:255', 'starts_with:/'],
            'provider_suspend_path' => ['nullable', 'string', 'max:255', 'starts_with:/'],
            'provider_cancel_path' => ['nullable', 'string', 'max:255', 'starts_with:/'],
            'provider_sync_path' => ['nullable', 'string', 'max:255', 'starts_with:/'],
        ];
    }
}
