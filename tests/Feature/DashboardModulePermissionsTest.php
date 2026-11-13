<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardModulePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_roles_are_member_accounts_and_system_roles_use_the_backend(): void
    {
        $superAdministrator = User::factory()->superAdmin()->create();
        $administrator = User::factory()->administrator()->create();
        $business = Business::factory()->create();
        $businessOwner = User::factory()->create();
        $businessAdministrator = User::factory()->create();
        $businessMember = User::factory()->create();
        $this->attachBusinessUser($business, $businessOwner, 'owner');
        $this->attachBusinessUser($business, $businessAdministrator, 'admin');
        $this->attachBusinessUser($business, $businessMember, 'member');

        $accounts = $this->actingAs($superAdministrator)
            ->getJson(route('super-admin.accounts.index'))
            ->assertOk()
            ->json('data');

        $administratorRecord = collect($accounts)->firstWhere('id', $administrator->id);
        $businessAdminRecord = collect($accounts)->firstWhere('id', $businessAdministrator->id);
        $this->assertSame('administrator', $administratorRecord['role_key']);
        $this->assertSame('member', $businessAdminRecord['role_key']);
        $this->assertSame('Member', $businessAdminRecord['role']);
        $this->assertSame('admin', $businessAdminRecord['businesses'][0]['role']);
        $this->assertSame('member', collect($accounts)->firstWhere('id', $businessOwner->id)['role_key']);
        $this->assertSame('member', collect($accounts)->firstWhere('id', $businessMember->id)['role_key']);

        $this->actingAs($administrator)->get('/super-admin')->assertOk();
        $this->get('/dashboard')->assertRedirect('/super-admin');
        $this->getJson(route('dashboard.data'))->assertForbidden();
        $this->actingAs($businessAdministrator)->get('/super-admin')->assertForbidden();
    }

    public function test_super_administrator_can_restrict_backend_modules_for_administrator(): void
    {
        $superAdministrator = User::factory()->superAdmin()->create();
        $administrator = User::factory()->administrator()->create();
        $permissions = $this->systemPermissionMatrix();
        $invalidPermissions = $permissions;
        $invalidPermissions['reports'] = ['read' => false, 'write' => true];

        $this->actingAs($superAdministrator)
            ->putJson(route('super-admin.accounts.update', $administrator), $this->accountData($administrator, [
                'permissions' => $invalidPermissions,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permissions');

        $permissions['overview'] = ['read' => false, 'write' => false];
        $permissions['accounts'] = ['read' => true, 'write' => false];
        $permissions['reports'] = ['read' => true, 'write' => false];

        $this->actingAs($superAdministrator)
            ->putJson(route('super-admin.accounts.update', $administrator), $this->accountData($administrator, [
                'permissions' => $permissions,
            ]))
            ->assertOk()
            ->assertJsonPath('account.permissions.overview.read', false);

        $this->assertDatabaseHas('dashboard_module_permissions', [
            'user_id' => $administrator->id,
            'module' => 'system_overview',
            'can_read' => false,
        ]);

        $this->actingAs($administrator)
            ->get('/super-admin')
            ->assertRedirect('/super-admin/businesses');
        $this->getJson(route('super-admin.overview'))->assertForbidden();
        $this->get('/super-admin/reports')->assertOk();
        $this->getJson(route('super-admin.workspace', 'reports'))->assertOk();
        $this->getJson(route('super-admin.accounts.index'))->assertOk();
        $this->putJson(route('super-admin.accounts.update', $administrator), [])
            ->assertForbidden();
    }

    public function test_only_super_administrators_can_manage_system_roles_or_module_permissions(): void
    {
        $superAdministrator = User::factory()->superAdmin()->create();
        $administrator = User::factory()->administrator()->create();
        $member = User::factory()->create();

        $newAccountData = $this->accountData(User::factory()->make(), [
            'first_name' => 'Member',
            'last_name' => 'Account',
            'email' => 'member-role-change@example.test',
            'role' => 'administrator',
            'password' => 'member-password',
            'password_confirmation' => 'member-password',
        ]);
        $this->actingAs($administrator)
            ->postJson(route('super-admin.accounts.store'), $newAccountData)
            ->assertForbidden();

        $this->putJson(route('super-admin.accounts.update', $administrator), $this->accountData($administrator, [
            'permissions' => $this->systemPermissionMatrix(),
        ]))->assertForbidden();

        $memberData = $this->accountData($member);
        unset($memberData['permissions']);
        $memberData['business_ids'] = [];
        $memberData['business_roles'] = [];
        $this->putJson(route('super-admin.accounts.update', $member), array_replace($memberData, [
            'role' => 'administrator',
        ]))->assertForbidden();

        $permissions = $this->systemPermissionMatrix();
        $createdAdministrator = $this->actingAs($superAdministrator)
            ->postJson(route('super-admin.accounts.store'), $this->accountData(User::factory()->make(), [
                'first_name' => 'Backend',
                'last_name' => 'Administrator',
                'email' => 'backend-administrator@example.test',
                'role' => 'administrator',
                'permissions' => $permissions,
                'password' => 'admin-password',
                'password_confirmation' => 'admin-password',
            ]))
            ->assertCreated()
            ->assertJsonPath('account.role_key', 'administrator')
            ->json('account');

        $this->assertDatabaseHas('users', [
            'id' => $createdAdministrator['id'],
            'account_type' => User::ACCOUNT_TYPE_ADMINISTRATOR,
        ]);
    }

    public function test_member_business_administrator_permissions_remain_separate_from_backend_permissions(): void
    {
        $superAdministrator = User::factory()->superAdmin()->create();
        $business = Business::factory()->create();
        $businessAdministrator = User::factory()->create();
        $businessMember = User::factory()->create();
        $this->attachBusinessUser($business, $businessAdministrator, 'admin');
        $this->attachBusinessUser($business, $businessMember, 'member');

        $this->actingAs($businessAdministrator)
            ->getJson(route('dashboard.members.index'))
            ->assertForbidden();

        $memberPermissions = $this->memberPermissionMatrix();
        $memberPermissions['users'] = ['read' => true, 'write' => false];
        $this->actingAs($superAdministrator)
            ->putJson(route('super-admin.accounts.update', $businessAdministrator), $this->accountData($businessAdministrator, [
                'business_ids' => [$business->id],
                'business_roles' => [['business_id' => $business->id, 'role' => 'admin']],
                'permissions' => $memberPermissions,
            ]))
            ->assertOk()
            ->assertJsonPath('account.role_key', 'member')
            ->assertJsonPath('account.permissions.users.read', true);

        $this->assertDatabaseHas('dashboard_module_permissions', [
            'user_id' => $businessAdministrator->id,
            'module' => 'users',
            'can_read' => true,
        ]);
        $this->assertTrue($businessAdministrator->fresh()->canAccessDashboardModule('users'));

        $this->actingAs($businessAdministrator->fresh())
            ->getJson(route('dashboard.members.index'))
            ->assertOk()
            ->assertJsonPath('can_write', false)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $businessMember->id);
        $this->get('/super-admin')->assertForbidden();
    }

    /**
     * @return array<string, array{read: bool, write: bool}>
     */
    private function systemPermissionMatrix(): array
    {
        return array_fill_keys(User::SYSTEM_ADMIN_MODULES, ['read' => true, 'write' => true]);
    }

    /**
     * @return array<string, array{read: bool, write: bool}>
     */
    private function memberPermissionMatrix(): array
    {
        $permissions = array_fill_keys(User::DASHBOARD_MODULES, ['read' => true, 'write' => true]);
        $permissions['users'] = ['read' => false, 'write' => false];

        return $permissions;
    }

    /**
     * @return array<string, mixed>
     */
    private function accountData(User $user, array $overrides = []): array
    {
        $user->loadMissing(['profile', 'businesses', 'dashboardModulePermissions']);
        $role = match ($user->account_type) {
            User::ACCOUNT_TYPE_SUPER_ADMIN => 'super_administrator',
            User::ACCOUNT_TYPE_ADMINISTRATOR => 'administrator',
            default => 'member',
        };
        $businessRoles = $user->businesses->map(fn (Business $business): array => [
            'business_id' => $business->id,
            'role' => $business->pivot->role,
        ])->all();

        $data = [
            'first_name' => $user->profile?->first_name ?? 'Member',
            'last_name' => $user->profile?->last_name,
            'email' => $user->email,
            'phone' => $user->profile?->phone,
            'job_title' => $user->profile?->job_title,
            'role' => $role,
            'business_ids' => $user->businesses->modelKeys(),
            'business_roles' => $businessRoles,
            'password' => null,
            'password_confirmation' => null,
        ];
        if ($role === 'administrator') {
            $data['permissions'] = $user->systemAdminModuleAccess();
        } elseif ($role === 'member') {
            $data['permissions'] = $user->dashboardModuleAccess();
        }

        return array_replace($data, $overrides);
    }

    private function attachBusinessUser(Business $business, User $user, string $role): void
    {
        $business->users()->attach($user->id, [
            'role' => $role,
            'access_level' => $role,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }
}
