<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentEvent;
use App\Services\Finance\ManualInvoicePaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('invoices.update') ?? false;
    }

    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $manualEventId = $invoice
            ? PaymentEvent::where('provider', ManualInvoicePaymentService::PROVIDER)
                ->where('invoice_id', $invoice->id)
                ->value('id')
            : null;

        return [
            'status' => ['required', 'string', Rule::in(['open', 'paid', 'void'])],
            'provider_transaction_id' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('payment_events', 'provider_transaction_id')->ignore($manualEventId),
            ],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'processed_at' => ['nullable', 'date'],
        ];
    }
}
