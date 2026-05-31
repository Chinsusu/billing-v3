<?php

namespace App\Http\Requests\Admin;

use App\Services\Finance\ManualInvoicePaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManualPaymentEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('paymentEvent');

        return ($this->user()?->can('payment_events.update') ?? false)
            && $event?->provider === ManualInvoicePaymentService::PROVIDER;
    }

    public function rules(): array
    {
        $event = $this->route('paymentEvent');

        return [
            'provider_transaction_id' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('payment_events', 'provider_transaction_id')->ignore($event?->id),
            ],
            'reference' => ['nullable', 'string', 'max:120'],
            'processed_at' => ['nullable', 'date'],
        ];
    }
}
