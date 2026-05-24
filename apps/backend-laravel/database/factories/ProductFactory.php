<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => Str::title($name),
            'type' => fake()->randomElement(['proxy', 'vps']),
            'status' => fake()->randomElement(['active', 'draft']),
            'price_amount' => fake()->numberBetween(50000, 500000),
            'currency' => 'VND',
            'duration_days' => 30,
            'description' => fake()->sentence(),
            'config' => [],
            'provider_account_id' => null,
            'provider_plan_code' => null,
            'provider_region' => null,
            'provider_provision_path' => null,
            'provider_options' => [],
            'lifecycle_source' => 'local_policy',
            'lifecycle_unit' => 'day',
            'lifecycle_count' => 30,
            'provider_lifecycle_path' => null,
            'provider_lifecycle_ordered_at_path' => null,
            'provider_lifecycle_expires_at_path' => null,
            'provider_lifecycle_date_format' => 'iso8601',
            'provider_lifecycle_timezone' => 'UTC',
            'provider_renew_path' => null,
            'provider_suspend_path' => null,
            'provider_cancel_path' => null,
            'provider_sync_path' => null,
        ];
    }
}
