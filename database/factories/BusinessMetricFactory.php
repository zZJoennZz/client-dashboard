<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessMetric>
 */
class BusinessMetricFactory extends Factory
{
    protected $model = BusinessMetric::class;

    public function definition(): array
    {
        $periodStart = now()->startOfMonth();

        return [
            'business_id' => Business::factory(),
            'metric_type' => 'website_visits',
            'value' => fake()->randomFloat(4, 0, 100000),
            'unit' => 'visits',
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
            'source' => 'analytics',
            'recorded_at' => now(),
        ];
    }
}
