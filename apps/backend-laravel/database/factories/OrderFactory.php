<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'paid',
            'subtotal_amount' => 99000,
            'total_amount' => 99000,
            'currency' => 'VND',
            'meta' => [],
            'paid_at' => now(),
        ];
    }
}
