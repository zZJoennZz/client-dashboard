<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessMetric;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MarketingActivity;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Report;
use App\Models\Resource;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardSpaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shell_and_deep_links_are_protected_and_react_mounted(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('dashboard.data'), ['Accept' => 'application/json'])->assertUnauthorized();
        $this->get('/dashboard/reports')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.index')
            ->assertSee('id="member-dashboard"', false)
            ->assertDontSee('Your workspace');

        $this->get('/dashboard/reports')->assertOk()->assertViewIs('dashboard.index');
        $this->get('/dashboard/not-a-dashboard-page')->assertOk()->assertViewIs('dashboard.index');
    }

    public function test_data_endpoint_only_returns_current_users_associated_business_content(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create([
            'company_name' => 'Authorized Roanoke Business',
            'settings' => ['category' => 'Retail'],
        ]);
        $otherBusiness = Business::factory()->create([
            'company_name' => 'Private Other Business',
        ]);

        $business->users()->attach($user->id, [
            'role' => 'owner',
            'access_level' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $plan = MembershipPlan::factory()->create([
            'name' => 'Community Plan',
            'is_default' => true,
        ]);
        Membership::factory()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'metadata' => ['payment_mode' => 'simulated'],
        ]);

        Report::factory()->create([
            'business_id' => $business->id,
            'title' => 'Published member report',
            'status' => 'published',
            'visibility' => 'member',
        ]);
        Report::factory()->create([
            'business_id' => $business->id,
            'title' => 'Private draft report',
            'status' => 'draft',
            'visibility' => 'private',
        ]);
        Report::factory()->create([
            'business_id' => $otherBusiness->id,
            'title' => 'Another business report',
            'status' => 'published',
            'visibility' => 'member',
        ]);

        Event::factory()->create([
            'business_id' => $business->id,
            'title' => 'Published business event',
            'status' => 'scheduled',
            'is_published' => true,
        ]);
        Event::factory()->create([
            'business_id' => $otherBusiness->id,
            'title' => 'Other business event',
            'status' => 'published',
            'is_published' => true,
        ]);

        Resource::factory()->create([
            'business_id' => null,
            'title' => 'Shared member resource',
            'status' => 'published',
            'visibility' => 'member',
        ]);
        Resource::factory()->create([
            'business_id' => $otherBusiness->id,
            'title' => 'Other business resource',
            'status' => 'published',
            'visibility' => 'member',
        ]);
        Resource::factory()->create([
            'business_id' => null,
            'title' => 'Private resource',
            'status' => 'draft',
            'visibility' => 'private',
        ]);

        MarketingActivity::factory()->create([
            'business_id' => $business->id,
            'title' => 'Scheduled member activity',
            'status' => 'scheduled',
            'notes' => 'Internal notes are not exposed',
        ]);
        MarketingActivity::factory()->create([
            'business_id' => $otherBusiness->id,
            'title' => 'Other business activity',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('business.name', 'Authorized Roanoke Business')
            ->assertJsonPath('business.category', 'Retail')
            ->assertJsonPath('membership.plan', 'Community Plan')
            ->assertJsonPath('membership.payment_is_simulated', true)
            ->assertJsonFragment(['title' => 'Published member report'])
            ->assertJsonFragment(['title' => 'Published business event'])
            ->assertJsonFragment(['title' => 'Shared member resource'])
            ->assertJsonFragment(['title' => 'Scheduled member activity'])
            ->assertJsonMissing(['title' => 'Private draft report'])
            ->assertJsonMissing(['title' => 'Another business report'])
            ->assertJsonMissing(['title' => 'Other business event'])
            ->assertJsonMissing(['title' => 'Other business resource'])
            ->assertJsonMissing(['title' => 'Private resource'])
            ->assertJsonMissing(['title' => 'Other business activity'])
            ->assertJsonMissing(['notes' => 'Internal notes are not exposed']);

        $this->assertStringNotContainsString('file_path', $response->getContent());
    }

    public function test_dashboard_data_handles_an_account_without_an_associated_business(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('business', null)
            ->assertJsonPath('membership', null)
            ->assertJsonPath('reports', [])
            ->assertJsonPath('events', [])
            ->assertJsonPath('marketing', [])
            ->assertJsonPath('activity', [])
            ->assertJsonPath('participation', [])
            ->assertJsonPath('attendance_history', [])
            ->assertJsonPath('engagement.rsvps_30_days', 0)
            ->assertJsonPath('engagement.upcoming_registered', 0)
            ->assertJsonPath('engagement.recorded_attendance', 0);
    }

    public function test_business_owner_can_edit_and_publish_their_own_profile_only(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $business = Business::factory()->create([
            'company_name' => 'Old Business Name',
            'slug' => 'old-business-name',
            'is_published' => false,
            'directory_visibility' => 'private',
        ]);
        $business->users()->attach($owner->id, [
            'role' => 'owner',
            'access_level' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('business.can_manage_profile', true)
            ->assertJsonPath('business.is_published', false)
            ->assertJsonPath('business.image_count', 0);

        $this->putJson(route('dashboard.businesses.profile.update', $business), [
            'company_name' => 'New Business Name',
            'category' => 'Retail',
            'additional_categories' => ['Arts'],
            'tagline' => 'A new local favorite',
            'contact_email' => 'hello@example.test',
            'website' => 'https://example.test',
            'country' => 'US',
        ])->assertOk();
        $this->assertSame('new-business-name', $business->fresh()->slug);
        $this->assertDatabaseHas('business_categories', ['name' => 'Retail']);
        $this->assertDatabaseHas('business_business_category', ['business_id' => $business->id]);

        $this->patchJson(route('dashboard.businesses.publication', $business), ['is_published' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');

        $imageContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        $this->postJson(route('dashboard.businesses.images.store', $business), [
            'images' => [
                UploadedFile::fake()->createWithContent('front.png', $imageContent),
                UploadedFile::fake()->createWithContent('inside.png', $imageContent),
            ],
            'alt_text' => ['Storefront', 'Inside the shop'],
        ])->assertCreated()->assertJsonPath('image_count', 2);

        $this->patchJson(route('dashboard.businesses.publication', $business), ['is_published' => true])
            ->assertOk()
            ->assertJsonPath('is_published', true)
            ->assertJsonPath('directory_visibility', 'public');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'business.member_profile_updated',
            'model_id' => $business->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'business.member_published',
            'model_id' => $business->id,
        ]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->putJson(route('dashboard.businesses.profile.update', $business), [
                'company_name' => 'Unauthorized Update',
                'category' => 'Retail',
            ])
            ->assertNotFound();
        $this->assertSame('New Business Name', $business->fresh()->company_name);

        $member = User::factory()->create();
        $business->users()->attach($member->id, [
            'role' => 'member',
            'access_level' => 'member',
            'status' => 'active',
            'is_primary' => false,
        ]);
        $this->actingAs($member)
            ->patchJson(route('dashboard.businesses.publication', $business), ['is_published' => false])
            ->assertForbidden();
    }

    public function test_owner_can_publish_a_registered_business_using_its_legacy_settings_category(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $business = Business::factory()->create([
            'company_name' => 'Registered Roanoke Shop',
            'slug' => 'registered-roanoke-shop',
            'settings' => ['category' => 'Food & drink'],
            'is_published' => false,
            'directory_visibility' => 'private',
        ]);
        $business->users()->attach($owner->id, [
            'role' => 'owner',
            'access_level' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $imageContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        $business->images()->createMany([
            ['file_path' => 'businesses/'.$business->id.'/images/front.png', 'sort_order' => 0, 'is_primary' => true],
            ['file_path' => 'businesses/'.$business->id.'/images/inside.png', 'sort_order' => 1, 'is_primary' => false],
        ]);
        Storage::disk('local')->put('businesses/'.$business->id.'/images/front.png', $imageContent);
        Storage::disk('local')->put('businesses/'.$business->id.'/images/inside.png', $imageContent);

        $this->actingAs($owner)
            ->patchJson(route('dashboard.businesses.publication', $business), ['is_published' => true])
            ->assertOk()
            ->assertJsonPath('is_published', true);

        $this->assertDatabaseHas('business_business_category', [
            'business_id' => $business->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('business_categories', ['name' => 'Food & drink']);
    }

    public function test_super_admin_can_view_data_across_all_businesses(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $businesses = Business::factory()->count(2)->sequence(
            ['company_name' => 'Admin Visible One'],
            ['company_name' => 'Admin Visible Two'],
        )->create();

        Membership::factory()->create(['business_id' => $businesses[0]->id]);
        Event::factory()->create([
            'business_id' => $businesses[1]->id,
            'title' => 'Admin-visible draft event',
            'status' => 'draft',
            'is_published' => false,
        ]);
        Resource::factory()->create([
            'business_id' => $businesses[0]->id,
            'title' => 'Admin-visible private resource',
            'status' => 'draft',
            'visibility' => 'private',
        ]);
        MarketingActivity::factory()->create([
            'business_id' => $businesses[1]->id,
            'title' => 'Admin-visible draft activity',
            'status' => 'draft',
        ]);

        foreach ($businesses as $business) {
            Report::factory()->create([
                'business_id' => $business->id,
                'title' => $business->company_name.' private report',
                'status' => 'draft',
                'visibility' => 'private',
            ]);
        }

        $this->actingAs($admin)
            ->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('user.is_super_admin', true)
            ->assertJsonPath('user.account_type', User::ACCOUNT_TYPE_SUPER_ADMIN)
            ->assertJsonPath('business', null)
            ->assertJsonPath('membership', null)
            ->assertJsonCount(2, 'businesses')
            ->assertJsonPath('businesses.0.membership.status', 'active')
            ->assertJsonFragment([
                'title' => 'Admin Visible One private report',
                'business_name' => 'Admin Visible One',
            ])
            ->assertJsonFragment([
                'title' => 'Admin Visible Two private report',
                'business_name' => 'Admin Visible Two',
            ])
            ->assertJsonFragment([
                'title' => 'Admin-visible draft event',
                'business_name' => 'Admin Visible Two',
            ])
            ->assertJsonFragment([
                'title' => 'Admin-visible private resource',
                'business_name' => 'Admin Visible One',
            ])
            ->assertJsonFragment([
                'title' => 'Admin-visible draft activity',
                'business_name' => 'Admin Visible Two',
            ]);
    }

    public function test_super_admin_routes_reject_guests_and_regular_accounts(): void
    {
        $this->get('/super-admin')->assertRedirect(route('login'));
        $this->getJson(route('super-admin.overview'))->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->get('/super-admin')->assertForbidden();
        $this->getJson(route('super-admin.overview'))->assertForbidden();
        $this->getJson(route('super-admin.businesses.index'))->assertForbidden();
        $this->getJson(route('super-admin.accounts.index'))->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/super-admin')
            ->assertOk()
            ->assertViewIs('super-admin.index')
            ->assertSee('id="super-admin"', false)
            ->assertSee(route('super-admin.overview'));
    }

    public function test_super_admin_can_filter_businesses_and_publish_with_audit_log(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $business = Business::factory()->create([
            'company_name' => 'Admin Managed Business',
            'is_published' => false,
        ]);
        $category = BusinessCategory::query()->create(['name' => 'Retail', 'slug' => 'retail']);
        $business->categories()->attach($category->id, ['is_primary' => true]);
        $business->images()->createMany([
            ['file_path' => 'businesses/'.$business->id.'/images/one.jpg', 'sort_order' => 0, 'is_primary' => true],
            ['file_path' => 'businesses/'.$business->id.'/images/two.jpg', 'sort_order' => 1, 'is_primary' => false],
        ]);
        $other = Business::factory()->create([
            'company_name' => 'Filtered Business',
            'is_published' => false,
        ]);

        $this->actingAs($admin)
            ->getJson(route('super-admin.businesses.index', ['search' => 'Admin Managed']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Admin Managed Business');

        $this->patchJson(route('super-admin.businesses.publication', $business), ['is_published' => true])
            ->assertOk()
            ->assertJsonPath('is_published', true);

        $this->assertDatabaseHas('clients', ['id' => $business->id, 'is_published' => true]);
        $this->assertDatabaseHas('clients', ['id' => $other->id, 'is_published' => false]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'business.published',
            'model_id' => $business->id,
        ]);

        $member = User::factory()->create(['email' => 'business-member@example.com']);
        $business->users()->attach($member->id, [
            'role' => 'owner',
            'access_level' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->getJson(route('super-admin.businesses.show', $business))
            ->assertOk()
            ->assertJsonPath('business.members.0.email', 'business-member@example.com')
            ->assertJsonPath('business.members.0.name', $member->profile->full_name)
            ->assertJsonPath('business.activity.reports', 0);

        $this->getJson(route('super-admin.accounts.index', ['search' => 'business-member']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'business-member@example.com');
    }

    public function test_super_admin_overview_reports_database_counts_and_recorded_money_by_currency(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $business = Business::factory()->create(['is_published' => true]);
        $order = Order::factory()->create([
            'business_id' => $business->id,
            'status' => 'submitted',
            'subtotal' => '100.00',
            'total' => '100.00',
            'currency' => 'USD',
            'payment_status' => 'partially_paid',
        ]);
        OrderPayment::factory()->create([
            'order_id' => $order->id,
            'amount' => '25.00',
            'currency' => 'USD',
            'status' => 'confirmed',
        ]);
        Order::factory()->create([
            'business_id' => $business->id,
            'status' => 'submitted',
            'subtotal' => '40.00',
            'total' => '40.00',
            'currency' => 'USD',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($admin)
            ->getJson(route('super-admin.overview'))
            ->assertOk()
            ->assertJsonPath('metrics.businesses_total', 1)
            ->assertJsonPath('metrics.businesses_published', 1)
            ->assertJsonPath('metrics.pending_orders', 2)
            ->assertJsonPath('metrics.confirmed_order_payments.0.currency', 'USD')
            ->assertJsonPath('metrics.confirmed_order_payments.0.amount', 25)
            ->assertJsonPath('metrics.outstanding_order_balance.0.amount', 115)
            ->assertJsonPath('metric_definitions.membership_growth', 'New membership records by creation month, not net member growth.');
    }

    public function test_super_admin_read_only_workspace_endpoints_return_real_records(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $plan = MembershipPlan::factory()->create();
        Membership::factory()->create(['business_id' => $business->id, 'plan_id' => $plan->id]);
        Resource::factory()->create(['business_id' => $business->id]);
        Report::factory()->create(['business_id' => $business->id]);
        $event = Event::factory()->create(['business_id' => $business->id]);
        EventRegistration::factory()->create(['event_id' => $event->id, 'user_id' => $user->id]);
        MarketingActivity::factory()->create(['business_id' => $business->id]);
        $order = Order::factory()->create(['business_id' => $business->id, 'user_id' => $user->id]);
        Service::factory()->create();
        OrderPayment::factory()->create(['order_id' => $order->id, 'recorded_by' => $admin->id]);
        BusinessMetric::factory()->create(['business_id' => $business->id]);
        AuditLog::record($admin, 'test.admin.action', $business);

        $this->actingAs($admin);

        foreach (['memberships', 'content', 'reports', 'events', 'marketing', 'orders', 'services', 'billing', 'analytics', 'audit'] as $section) {
            $this->getJson(route('super-admin.workspace', ['section' => $section]))
                ->assertOk()
                ->assertJsonPath('meta.total', 1);
        }

        $response = $this->getJson(route('super-admin.workspace', ['section' => 'reports']))->assertOk();
        $this->assertStringNotContainsString('file_path', $response->getContent());
        $this->getJson('/super-admin/api/workspace/unknown')->assertNotFound();
    }
}
