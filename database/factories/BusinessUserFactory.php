<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessUser>
 */
class BusinessUserFactory extends Factory
{
    protected $model = BusinessUser::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
            'access_level' => 'member',
            'status' => 'active',
            'is_primary' => false,
            'invited_at' => now(),
            'accepted_at' => now(),
        ];
    }
}
