<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceProviderPlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_service_provider_can_update_global_pricing_and_future_registrations_use_it(): void
    {
        $this->getJson(route('super-admin.settings.show'))->assertUnauthorized();

        $regular = User::factory()->create();
        $this->actingAs($regular)
            ->putJson(route('super-admin.settings.membership-pricing.update'), [
                'amount' => '250.00',
                'period' => 'month',
            ])
            ->assertForbidden();

        $provider = User::factory()->superAdmin()->create();
        $oldPlan = MembershipPlan::factory()->create([
            'name' => 'Previous membership pricing',
            'price' => 99.00,
            'billing_interval' => 'year',
            'is_default' => true,
        ]);
        $business = Business::factory()->create();
        Membership::factory()->create([
            'business_id' => $business->id,
            'plan_id' => $oldPlan->id,
        ]);

        $this->actingAs($provider)
            ->getJson(route('super-admin.settings.show'))
            ->assertOk()
            ->assertJsonPath('website_content.status', 'planned');

        $this->putJson(route('super-admin.settings.membership-pricing.update'), [
            'amount' => '250.00',
            'period' => 'month',
        ])
            ->assertOk()
            ->assertJsonPath('membership_pricing.amount', '250.00')
            ->assertJsonPath('message', 'Public membership pricing was updated. Registration still does not collect payment.');

        $newPlan = MembershipPlan::query()->where('is_default', true)->firstOrFail();
        $this->assertSame('250.00', $newPlan->price);
        $this->assertSame('month', $newPlan->billing_interval);
        $this->assertFalse($oldPlan->fresh()->is_default);
        $this->assertSame('99.00', $oldPlan->fresh()->price);
        $this->assertSame('250.00', PlatformSetting::getValue('membership.amount'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.membership_pricing_updated']);

        $this->get(route('pricing'))->assertOk()->assertSee('$250.00 / month');

        $this->post(route('logout'))->assertRedirect('/');
        $this->post(route('register.store'), [
            'company_name' => 'New Local Business',
            'first_name' => 'Jordan',
            'last_name' => 'Member',
            'email' => 'jordan-new-business@example.com',
            'category' => 'Retail',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $newBusiness = Business::query()->where('company_name', 'New Local Business')->firstOrFail();
        $newMembership = Membership::query()->where('business_id', $newBusiness->id)->firstOrFail();
        $this->assertSame($newPlan->id, $newMembership->plan_id);
        $this->assertSame('paid', $newMembership->payment_status);
        $this->assertSame('simulated', data_get($newMembership->metadata, 'payment_mode'));
    }

    public function test_invalid_platform_pricing_is_rejected_without_partial_updates(): void
    {
        $provider = User::factory()->superAdmin()->create();

        $this->actingAs($provider)
            ->putJson(route('super-admin.settings.membership-pricing.update'), [
                'amount' => '-15.50',
                'period' => 'weekly',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'period']);

        $this->assertDatabaseCount('platform_settings', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'platform.membership_pricing_updated']);
    }
}
