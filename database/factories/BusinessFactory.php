<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        $companyName = fake()->company();

        return [
            'company_name' => $companyName,
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->paragraph(),
            'logo_url' => null,
            'logo_path' => null,
            'primary_color' => '#3B82F6',
            'secondary_color' => '#10B981',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'contact_name' => fake()->name(),
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'website' => 'https://' . fake()->domainName(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'status' => 'active',
            'is_active' => true,
            'is_published' => true,
            'is_featured' => false,
            'directory_visibility' => 'public',
            'published_at' => now(),
            'notes' => null,
            'settings' => [
                'directory_display' => 'public',
            ],
        ];
    }
}
