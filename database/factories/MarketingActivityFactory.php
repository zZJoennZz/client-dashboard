<?php

namespace Database\Factories;

use App\Models\MarketingActivity;
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
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'promotion_type' => fake()->randomElement(config('marketing.promotion_types')),
            'platforms' => [],
            'scheduled_at' => now()->addDays(7),
            'completed_at' => null,
            'status' => 'scheduled',
            'notes' => null,
        ];
    }
}
