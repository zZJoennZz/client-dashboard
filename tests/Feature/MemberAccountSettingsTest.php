<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_update_profile_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('dashboard.account.profile.update'), [
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'phone' => '555-0100',
            'job_title' => 'Director',
            'address_line_1' => '12 Main Street',
            'address_line_2' => '',
            'city' => 'Roanoke',
            'state' => 'VA',
            'postal_code' => '24011',
            'country' => 'US',
        ])->assertOk()
            ->assertJsonPath('message', 'Profile updated successfully.');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'phone' => '555-0100',
            'job_title' => 'Director',
        ]);
    }

    public function test_profile_validation_rejects_invalid_personal_information(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/dashboard/settings')->putJson(route('dashboard.account.profile.update'), [
            'first_name' => '',
            'country' => 'USA',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'country']);
    }

    public function test_member_cannot_change_protected_account_fields_through_profile_update(): void
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;
        $originalPassword = $user->password;

        $this->actingAs($user)->putJson(route('dashboard.account.profile.update'), [
            'first_name' => 'Updated',
            'email' => 'attacker@example.com',
            'password' => 'unsafe-password',
            'account_type' => User::ACCOUNT_TYPE_SUPER_ADMIN,
            'business_id' => 999,
        ])->assertOk();

        $user->refresh();
        $this->assertSame($originalEmail, $user->email);
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame(User::ACCOUNT_TYPE_REGULAR, $user->account_type);
    }

    public function test_member_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($user)->putJson(route('dashboard.account.password.update'), [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_password_change_requires_correct_current_password_and_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($user)->putJson(route('dashboard.account.password.update'), [
            'current_password' => 'incorrect-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->actingAs($user)->putJson(route('dashboard.account.password.update'), [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_change_enforces_minimum_length(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($user)->putJson(route('dashboard.account.password.update'), [
            'current_password' => 'current-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_account_update_routes_require_authentication(): void
    {
        $this->putJson(route('dashboard.account.profile.update'), [
            'first_name' => 'Unauthorized',
        ])->assertUnauthorized();

        $this->putJson(route('dashboard.account.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnauthorized();
    }
}
