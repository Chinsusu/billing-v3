<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('invoices.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_email' => ['required', 'email', 'exists:users,email'],
            'total_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', Rule::in(['VND'])],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
