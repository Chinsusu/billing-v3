<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_code' => 'proxy-vn-30d',
            'product_name' => 'Vietnam Proxy 30 Days',
            'product_type' => 'proxy',
            'quantity' => 1,
            'unit_amount' => 99000,
            'subtotal_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
            'config_snapshot' => [],
        ];
    }
}
