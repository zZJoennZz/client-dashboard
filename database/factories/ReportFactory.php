<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'reporting_period' => '2026-Q1',
            'file_name' => 'report-' . fake()->uuid() . '.pdf',
            'file_path' => 'reports/report-' . fake()->uuid() . '.pdf',
            'file_disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => 12000,
            'status' => 'ready',
            'visibility' => 'private',
            'created_by' => User::factory(),
            'published_at' => now(),
        ];
    }
}
