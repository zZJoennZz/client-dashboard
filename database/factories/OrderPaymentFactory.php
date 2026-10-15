<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderPayment>
 */
class OrderPaymentFactory extends Factory
{
    protected $model = OrderPayment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => 50.00,
            'currency' => 'USD',
            'payment_date' => now()->toDateString(),
            'method' => 'check',
            'reference' => fake()->optional()->bothify('REF-####'),
            'notes' => null,
            'recorded_by' => User::factory(),
            'status' => 'confirmed',
        ];
    }
}
