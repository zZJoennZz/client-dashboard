<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\MarketingActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingActivity>
 */
class MarketingActivityFactory extends Factory
{
    protected $model = MarketingActivity::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'scheduled_at' => now()->addDays(7),
            'completed_at' => null,
            'status' => 'scheduled',
            'notes' => null,
            'responsible_admin_id' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
