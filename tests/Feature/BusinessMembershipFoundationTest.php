<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMembershipFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_linked_to_multiple_businesses(): void
    {
        $user = User::factory()->create();
        $businessOne = Business::factory()->create(['slug' => 'roanoke-one']);
        $businessTwo = Business::factory()->create(['slug' => 'roanoke-two']);

        $user->businesses()->attach($businessOne->id, [
            'role' => 'owner',
            'access_level' => 'admin',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $user->businesses()->attach($businessTwo->id, [
            'role' => 'member',
            'access_level' => 'member',
            'status' => 'active',
            'is_primary' => false,
        ]);

        $this->assertCount(2, $user->fresh()->businesses()->get());
        $this->assertCount(2, BusinessUser::where('user_id', $user->id)->get());
    }

    public function test_business_user_has_unique_user_business_association(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        BusinessUser::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'access_level' => 'admin',
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        BusinessUser::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'member',
            'access_level' => 'member',
            'status' => 'active',
        ]);
    }

    public function test_membership_plan_tracks_configurable_pricing(): void
    {
        $plan = MembershipPlan::factory()->create([
            'name' => 'Roanoke Standard',
            'slug' => 'roanoke-standard',
            'price' => 89.99,
            'currency' => 'USD',
            'billing_interval' => 'monthly',
        ]);

        $this->assertSame('89.99', (string) $plan->price);
        $this->assertSame('USD', $plan->currency);
    }

    public function test_membership_can_be_activated_without_stripe(): void
    {
        $business = Business::factory()->create();
        $plan = MembershipPlan::factory()->create(['price' => 109.00]);
        $membership = Membership::factory()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'pending_activation',
            'payment_status' => 'paid',
        ]);

        $membership->activate();
        $membership->refresh();

        $this->assertSame('active', $membership->status);
        $this->assertNotNull($membership->activated_at);
        $this->assertSame($business->id, $membership->business->id);
        $this->assertSame($plan->id, $membership->plan->id);
    }
}
