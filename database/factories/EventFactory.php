<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startsAt = now()->addDays(10);

        return [
            'business_id' => Business::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'meeting_url' => 'https://zoom.example.com/' . fake()->uuid(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'status' => 'scheduled',
            'is_published' => true,
            'capacity' => 50,
            'created_by' => User::factory(),
        ];
    }
}
