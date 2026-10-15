<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'price' => 59.00,
            'currency' => 'USD',
            'billing_interval' => 'monthly',
            'is_active' => true,
            'is_default' => false,
            'trial_days' => 0,
            'sort_order' => 0,
        ];
    }
}
