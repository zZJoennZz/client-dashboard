<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'resource_type' => 'guide',
            'file_name' => 'resource-' . fake()->uuid() . '.pdf',
            'file_path' => 'resources/resource-' . fake()->uuid() . '.pdf',
            'file_url' => 'https://example.com/resources/' . fake()->uuid(),
            'status' => 'published',
            'visibility' => 'member',
            'is_featured' => false,
            'created_by' => User::factory(),
            'published_at' => now(),
        ];
    }
}
