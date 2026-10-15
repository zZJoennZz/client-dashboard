<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'RR-' . Str::upper(Str::random(12)),
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'subtotal' => 0,
            'total' => 0,
            'currency' => 'USD',
            'notes' => null,
        ];
    }
}
