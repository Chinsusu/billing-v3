<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
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
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
