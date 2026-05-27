<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    private const DEFAULT_DESCRIPTION = 'Nạp Tiền';

    public function authorize(): bool
    {
        return $this->user()?->can('invoices.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        if ($description === null || (is_string($description) && trim($description) === '')) {
            $this->merge(['description' => self::DEFAULT_DESCRIPTION]);

            return;
        }

        if (is_string($description)) {
            $this->merge(['description' => trim($description)]);
        }
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
