<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'order_item_id' => OrderItem::factory(),
            'product_id' => Product::factory(),
            'product_code' => 'proxy-vn-30d',
            'product_name' => 'Vietnam Proxy 30 Days',
            'product_type' => 'proxy',
            'status' => 'pending_provision',
            'config' => [],
            'meta' => [],
        ];
    }
}
