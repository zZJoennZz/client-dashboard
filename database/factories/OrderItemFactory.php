<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $price = fake()->randomFloat(2, 10, 500);
        $quantity = fake()->numberBetween(1, 8);

        return [
            'order_id' => Order::factory(),
            'service_id' => Service::factory(),
            'service_name' => fake()->words(3, true),
            'service_description' => fake()->sentence(),
            'unit_price' => $price,
            'quantity' => $quantity,
            'line_total' => number_format($price * $quantity, 2, '.', ''),
        ];
    }
}
