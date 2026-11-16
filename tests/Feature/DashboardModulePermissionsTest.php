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

        $this->actingAs($administrator)->get('/system')->assertOk();
        $this->get('/dashboard')->assertRedirect('/system');
        $this->getJson(route('dashboard.data'))->assertForbidden();
        $this->actingAs($businessAdministrator)->get('/system')->assertForbidden();
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
            ->get('/system')
            ->assertRedirect('/system/businesses');
        $this->getJson(route('super-admin.overview'))->assertForbidden();
        $this->get('/system/reports')->assertOk();
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

        $this->postJson(route('super-admin.accounts.store'), $this->accountData(User::factory()->make(), [
            'first_name' => 'Second',
            'email' => 'second-system-admin@example.test',
            'role' => 'system_administrator',
            'password' => 'admin-password',
            'password_confirmation' => 'admin-password',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->actingAs($administrator)->postJson(route('super-admin.access-roles.store'), [
            'name' => 'Not allowed',
            'applies_to' => 'administrator',
            'permissions' => $permissions,
        ])->assertForbidden();
    }

    public function test_super_administrator_can_delete_an_unassigned_access_role(): void
    {
        $systemAdministrator = User::factory()->superAdmin()->create();
        $role = $this->actingAs($systemAdministrator)
            ->postJson(route('super-admin.access-roles.store'), [
                'name' => 'Temporary administrator role',
                'applies_to' => 'administrator',
                'permissions' => $this->systemPermissionMatrix(),
            ])
            ->assertCreated()
            ->json('role');

        $this->deleteJson(route('super-admin.access-roles.destroy', $role['id']))
            ->assertNoContent();

        $this->assertDatabaseMissing('access_roles', ['id' => $role['id']]);
    }

    public function test_access_role_presets_apply_to_administrator_and_member_permissions_and_remain_attached(): void
    {
        $systemAdministrator = User::factory()->superAdmin()->create();
        $business = Business::factory()->create();
        $administratorPermissions = array_fill_keys(User::SYSTEM_ADMIN_MODULES, ['read' => false, 'write' => false]);
        $administratorPermissions['overview'] = ['read' => true, 'write' => false];
        $memberPermissions = array_fill_keys(User::DASHBOARD_MODULES, ['read' => false, 'write' => false]);
        $memberPermissions['business'] = ['read' => true, 'write' => false];

        $administratorRole = $this->actingAs($systemAdministrator)
            ->postJson(route('super-admin.access-roles.store'), [
                'name' => 'Read-only operations',
                'applies_to' => 'administrator',
                'permissions' => $administratorPermissions,
            ])
            ->assertCreated()
            ->json('role');

        $administratorData = $this->accountData(User::factory()->make(), [
            'first_name' => 'Preset',
            'last_name' => 'Administrator',
            'email' => 'preset-admin@example.test',
            'role' => 'administrator',
            'access_role_id' => $administratorRole['id'],
            'password' => 'admin-password',
            'password_confirmation' => 'admin-password',
        ]);
        unset($administratorData['permissions']);
        $administrator = $this->postJson(route('super-admin.accounts.store'), $administratorData)
            ->assertCreated()
            ->assertJsonPath('account.access_role_id', $administratorRole['id'])
            ->assertJsonPath('account.access_role_name', 'Read-only operations')
            ->assertJsonPath('account.permissions.overview.read', true)
            ->assertJsonPath('account.permissions.overview.write', false)
            ->json('account');

        $memberRole = $this->postJson(route('super-admin.access-roles.store'), [
            'name' => 'Business viewer',
            'applies_to' => 'member',
            'permissions' => $memberPermissions,
        ])->assertCreated()->json('role');
        $memberData = $this->accountData(User::factory()->make(), [
            'first_name' => 'Business',
            'last_name' => 'Viewer',
            'email' => 'business-viewer@example.test',
            'role' => 'member',
            'business_ids' => [$business->id],
            'business_roles' => [['business_id' => $business->id, 'role' => 'admin']],
            'access_role_id' => $memberRole['id'],
            'password' => 'member-password',
            'password_confirmation' => 'member-password',
        ]);
        unset($memberData['permissions']);
        $member = $this->postJson(route('super-admin.accounts.store'), $memberData)
            ->assertCreated()
            ->assertJsonPath('account.access_role_id', $memberRole['id'])
            ->assertJsonPath('account.access_role_name', 'Business viewer')
            ->assertJsonPath('account.permissions.business.read', true)
            ->assertJsonPath('account.permissions.business.write', false)
            ->json('account');

        $this->assertDatabaseHas('users', [
            'id' => $administrator['id'],
            'access_role_id' => $administratorRole['id'],
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $member['id'],
            'access_role_id' => $memberRole['id'],
        ]);
        $this->assertDatabaseHas('dashboard_module_permissions', [
            'user_id' => $administrator['id'],
            'module' => 'system_overview',
            'can_read' => true,
            'can_write' => false,
        ]);
        $this->assertDatabaseHas('dashboard_module_permissions', [
            'user_id' => $member['id'],
            'module' => 'business',
            'can_read' => true,
            'can_write' => false,
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
        $this->get('/system')->assertForbidden();
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
