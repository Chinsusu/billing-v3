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
        ];
    }
}
