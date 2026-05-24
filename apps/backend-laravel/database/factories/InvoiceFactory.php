<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $amount = 99000;

        return [
            'user_id' => User::factory(),
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'open',
            'total_amount' => $amount,
            'currency' => 'VND',
            'description' => 'Test invoice',
            'lines' => [
                ['description' => 'Test invoice', 'amount' => $amount],
            ],
        ];
    }
}
