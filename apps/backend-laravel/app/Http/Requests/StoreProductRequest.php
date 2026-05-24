<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:products,code'],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['proxy', 'vps'])],
            'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
            'price_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'description' => ['nullable', 'string', 'max:2000'],
            'provider_account_id' => ['nullable', 'uuid', 'exists:provisioning_provider_accounts,id'],
            'provider_plan_code' => ['nullable', 'string', 'max:120'],
            'provider_region' => ['nullable', 'string', 'max:80'],
            'provider_provision_path' => ['nullable', 'string', 'max:255', 'starts_with:/'],
            'provider_options' => ['nullable', 'json'],
        ];
    }
}
