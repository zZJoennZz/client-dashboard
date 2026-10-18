<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthenticationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_business_user_and_simulated_active_membership(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertViewIs('auth.register')
            ->assertSee('Stripe checkout will be added here later.')
            ->assertSee('name="_token"', false);

        $response = $this->post(route('register.store'), [
            'company_name' => 'Roanoke Test Bakery',
            'first_name' => 'Jordan',
            'last_name' => 'Baker',
            'email' => 'jordan@example.com',
            'phone' => '540-555-0134',
            'website' => 'https://example.com',
            'category' => 'Food & drink',
            'description' => 'A neighborhood bakery.',
            'address_line_1' => '123 Market Street',
            'city' => 'Roanoke',
            'state' => 'Virginia',
            'postal_code' => '24011',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'terms' => '1',
            'account_type' => User::ACCOUNT_TYPE_SUPER_ADMIN,
        ]);

        $user = User::where('email', 'jordan@example.com')->firstOrFail();
        $business = Business::where('company_name', 'Roanoke Test Bakery')->firstOrFail();
        $membership = Membership::where('business_id', $business->id)->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Jordan Baker', $user->profile->full_name);
        $this->assertSame('Jordan', $user->profile->first_name);
        $this->assertSame('Baker', $user->profile->last_name);
        $this->assertSame('540-555-0134', $user->profile->phone);
        $this->assertSame(User::ACCOUNT_TYPE_REGULAR, $user->account_type);
        $this->assertFalse(Schema::hasColumn('users', 'name'));
        $this->assertFalse(Schema::hasColumn('users', 'phone'));
        $this->assertFalse(Schema::hasColumn('users', 'job_title'));
        $this->assertFalse(Schema::hasColumn('users', 'client_id'));
        $this->assertTrue(Schema::hasColumn('users', 'account_type'));
        $this->assertTrue(Schema::hasColumn('user_profiles', 'first_name'));
        $this->assertTrue(Schema::hasColumn('user_profiles', 'last_name'));
        $this->assertTrue($business->users()->whereKey($user->id)->exists());
        $this->assertTrue($business->businessUsers()->where('user_id', $user->id)->value('is_primary'));
        $this->assertSame('Food & drink', $business->categories()->wherePivot('is_primary', true)->value('name'));
        $this->assertSame('Food & drink', data_get($business->settings, 'category'));
        $this->assertSame('active', $membership->status);
        $this->assertSame('paid', $membership->payment_status);
        $this->assertSame('simulated', data_get($membership->metadata, 'payment_mode'));
        $this->assertNotNull($membership->plan);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="member-dashboard"', false)
            ->assertSee(route('dashboard.data'));

        $this->get(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('business.name', 'Roanoke Test Bakery')
            ->assertJsonPath('membership.status', 'active')
            ->assertJsonPath('membership.payment_is_simulated', true);
    }

    public function test_registration_requires_valid_confirmation_and_terms(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'company_name' => 'Needs Consent LLC',
            'first_name' => 'Taylor',
            'last_name' => 'Example',
            'email' => 'taylor@example.com',
            'category' => 'Retail',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertRedirect(route('register'))
            ->assertSessionHasErrors(['password', 'terms']);
        $this->assertDatabaseMissing('users', ['email' => 'taylor@example.com']);
        $this->assertDatabaseMissing('clients', ['company_name' => 'Needs Consent LLC']);
    }

    public function test_login_redirects_to_dashboard_and_logout_ends_the_session(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'StrongPassword123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'member@example.com',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('member_activities', [
            'user_id' => $user->id,
            'activity_type' => 'login',
        ]);
        $this->get(route('dashboard'))->assertOk()->assertSee('id="member-dashboard"', false);

        $this->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_super_admin_login_redirects_to_the_provider_dashboard_even_with_a_member_intended_url(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'email' => 'provider@example.com',
            'password' => 'StrongPassword123',
        ]);

        $this->withSession(['url.intended' => route('dashboard')])
            ->post(route('login.store'), [
                'email' => 'provider@example.com',
                'password' => 'StrongPassword123',
            ])
            ->assertRedirect(route('super-admin.spa'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('super-admin.spa'))
            ->assertOk()
            ->assertSee('id="super-admin"', false);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_configured_super_admin_emails_are_promoted_by_the_seeder(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $regular = User::factory()->create(['email' => 'member@example.com']);

        config([
            'accounts.super_admin_emails' => ['ADMIN@example.com', 'new-admin@example.com'],
            'accounts.super_admin_initial_password' => 'pokemon14',
        ]);
        $this->seed(SuperAdminSeeder::class);

        $this->assertTrue($admin->fresh()->isSuperAdmin());
        $this->assertTrue(password_verify('pokemon14', $admin->fresh()->password));
        $this->assertFalse($regular->fresh()->isSuperAdmin());

        $newAdmin = User::query()->where('email', 'new-admin@example.com')->firstOrFail();
        $this->assertTrue($newAdmin->isSuperAdmin());
        $this->assertTrue(password_verify('pokemon14', $newAdmin->password));
        $this->assertNotNull($newAdmin->email_verified_at);
        $this->assertSame('Super Admin', $newAdmin->profile->first_name);

        $this->seed(SuperAdminSeeder::class);
        $this->assertSame(2, User::query()->where('account_type', User::ACCOUNT_TYPE_SUPER_ADMIN)->count());
    }
}
