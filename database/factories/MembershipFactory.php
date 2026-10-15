<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'plan_id' => MembershipPlan::factory(),
            'status' => 'active',
            'payment_status' => 'paid',
            'start_date' => now()->toDateString(),
            'activated_at' => now(),
            'end_date' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'metadata' => [
                'source' => 'registration',
            ],
        ];
    }
}
