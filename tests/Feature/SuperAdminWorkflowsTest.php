<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Report;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuperAdminWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_edit_and_publish_businesses_and_regular_users_are_denied(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $regular = User::factory()->create();

        $this->actingAs($regular)
            ->postJson(route('super-admin.businesses.store'), $this->businessData())
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('super-admin.businesses.store'), $this->businessData())
            ->assertCreated()
            ->assertJsonPath('name', 'Roanoke Bakery');

        $business = Business::query()->where('company_name', 'Roanoke Bakery')->firstOrFail();
        $business->images()->createMany([
            ['file_path' => 'businesses/'.$business->id.'/images/one.jpg', 'sort_order' => 0, 'is_primary' => true],
            ['file_path' => 'businesses/'.$business->id.'/images/two.jpg', 'sort_order' => 1, 'is_primary' => false],
        ]);
        $this->putJson(route('super-admin.businesses.update', $business), $this->businessData([
            'company_name' => 'Roanoke Bread Co.',
            'contact_email' => 'hello@bread.example',
        ]))->assertOk();

        $this->patchJson(route('super-admin.businesses.publication', $business), ['is_published' => true])->assertOk();
        $this->assertSame('Roanoke Bread Co.', $business->fresh()->company_name);
        $this->assertTrue($business->fresh()->is_published);
        $this->assertDatabaseHas('audit_logs', ['action' => 'business.created', 'model_id' => $business->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'business.updated', 'model_id' => $business->id]);
    }

    public function test_business_profile_fields_categories_hours_highlights_and_video_are_managed(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $created = $this->actingAs($admin)
            ->postJson(route('super-admin.businesses.store'), $this->businessData([
                'company_name' => 'Roanoke Studio',
                'category' => 'Arts',
                'additional_categories' => ['Retail'],
                'tagline' => 'Made in the valley',
                'year_established' => 2018,
                'specialties' => ['Ceramics', 'Printmaking'],
                'hours' => [[
                    'day_of_week' => 1,
                    'opens_at' => '09:00',
                    'closes_at' => '17:00',
                    'is_closed' => false,
                    'by_appointment' => false,
                ]],
                'highlights' => [[
                    'name' => 'Custom pottery',
                    'description' => 'Made to order.',
                    'price_information' => 'From $40',
                    'is_active' => true,
                ]],
            ]))
            ->assertCreated()
            ->json();
        $business = Business::query()->findOrFail($created['id']);

        $this->assertSame(['Arts', 'Retail'], $business->categories()->pluck('name')->all());
        $this->assertDatabaseHas('business_hours', [
            'business_id' => $business->id,
            'day_of_week' => 1,
            'opens_at' => '09:00',
        ]);
        $this->assertDatabaseHas('business_highlights', [
            'business_id' => $business->id,
            'name' => 'Custom pottery',
        ]);

        $this->putJson(route('super-admin.businesses.update', $business), $this->businessData([
            'company_name' => 'Roanoke Studio',
            'category' => 'Arts',
            'additional_categories' => ['Retail'],
            'is_featured' => true,
        ]))->assertOk();

        $this->assertTrue($business->fresh()->is_featured);
        $this->actingAs($admin)->postJson(route('super-admin.businesses.video.update', $business), [
            'source_type' => 'youtube',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])->assertOk()->assertJsonPath('source_type', 'youtube');

        $this->postJson(route('super-admin.businesses.video.update', $business), [
            'source_type' => 'youtube',
            'youtube_url' => 'https://untrusted.example/video/dQw4w9WgXcQ',
        ])->assertUnprocessable();
        $this->postJson(route('super-admin.businesses.video.update', $business), [
            'source_type' => 'external',
            'external_url' => 'https://example.test/watch',
        ])->assertUnprocessable();
    }

    public function test_business_gallery_upload_publication_minimum_and_private_media_authorization(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $regular = User::factory()->create();
        $business = Business::factory()->create([
            'is_published' => false,
            'directory_visibility' => 'private',
        ]);
        $category = BusinessCategory::query()->create(['name' => 'Retail', 'slug' => 'retail']);
        $business->categories()->attach($category->id, ['is_primary' => true]);
        $imageContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');

        $this->actingAs($admin)->postJson(route('super-admin.businesses.images.store', $business), [
            'images' => [UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain')],
        ])->assertUnprocessable();

        $first = $this->actingAs($admin)->postJson(route('super-admin.businesses.images.store', $business), [
            'images' => [UploadedFile::fake()->createWithContent('front.png', $imageContent)],
            'alt_text' => ['Front of the shop'],
        ])->assertCreated()->json('data.0');
        $firstImage = $business->images()->findOrFail($first['id']);

        $this->actingAs($regular)->get(route('business-media.images', $firstImage))->assertNotFound();
        $this->actingAs($admin)->get(route('business-media.images', $firstImage))->assertOk();
        $this->actingAs($admin)
            ->patchJson(route('super-admin.businesses.publication', $business), ['is_published' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');

        $this->actingAs($admin)->postJson(route('super-admin.businesses.images.store', $business), [
            'images' => [UploadedFile::fake()->createWithContent('inside.png', $imageContent)],
        ])->assertCreated();

        $this->patchJson(route('super-admin.businesses.publication', $business), ['is_published' => true])
            ->assertOk()
            ->assertJsonPath('is_published', true);
        $this->get(route('business-media.images', $firstImage))->assertOk();
        $this->deleteJson(route('super-admin.businesses.images.destroy', [$business, $firstImage]))
            ->assertUnprocessable();

        $this->patchJson(route('super-admin.businesses.publication', $business), ['is_published' => false])->assertOk();
        $this->deleteJson(route('super-admin.businesses.images.destroy', [$business, $firstImage]))->assertOk();
        $this->get(route('business-media.images', $firstImage))->assertNotFound();
    }

    public function test_membership_adjustments_are_audited_without_changing_simulated_payment_status(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $membership = Membership::factory()->create([
            'status' => 'active',
            'payment_status' => 'paid',
            'metadata' => ['payment_mode' => 'simulated'],
            'end_date' => '2026-12-01',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('super-admin.memberships.update', $membership), ['end_date' => '2027-01-01'])
            ->assertOk()
            ->assertJsonPath('end_date', '2027-01-01');

        $this->postJson(route('super-admin.memberships.extend', $membership), ['days' => 30])->assertOk();

        $membership->refresh();
        $this->assertSame('active', $membership->status);
        $this->assertSame('paid', $membership->payment_status);
        $this->assertSame('2027-01-31', $membership->end_date->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.adjusted', 'model_id' => $membership->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.complimentary_extension', 'model_id' => $membership->id]);
    }

    public function test_member_event_registration_enforces_association_duplicate_and_capacity_rules(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $business = Business::factory()->create();
        $member = User::factory()->create();
        $otherMember = User::factory()->create();
        $this->attachMember($business, $member);
        $this->attachMember($business, $otherMember);

        $event = Event::factory()->create([
            'business_id' => $business->id,
            'capacity' => 1,
            'registration_enabled' => true,
            'registration_deadline' => now()->addDay(),
        ]);

        $this->actingAs($member)
            ->postJson(route('dashboard.events.register', $event))
            ->assertCreated();
        $this->postJson(route('dashboard.events.register', $event))->assertUnprocessable();

        $this->actingAs($otherMember)
            ->postJson(route('dashboard.events.register', $event))
            ->assertUnprocessable();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->postJson(route('dashboard.events.register', $event))
            ->assertNotFound();

        $this->actingAs($admin)
            ->postJson(route('super-admin.events.store'), [
                'business_id' => $business->id,
                'title' => 'Admin-created gathering',
                'starts_at' => now()->addDays(3)->toDateTimeString(),
                'ends_at' => now()->addDays(3)->addHour()->toDateTimeString(),
                'status' => 'draft',
                'is_published' => false,
                'registration_enabled' => true,
            ])
            ->assertCreated();
    }

    public function test_business_report_download_is_private_to_associated_business_members(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('admin/reports/private.pdf', 'private report');
        $business = Business::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $this->attachMember($business, $member);
        $report = Report::factory()->create([
            'business_id' => $business->id,
            'file_path' => 'admin/reports/private.pdf',
            'file_disk' => 'local',
            'status' => 'published',
            'visibility' => 'member',
        ]);

        $this->actingAs($other)->get(route('dashboard.files.reports', $report))->assertNotFound();
        $this->actingAs($member)->get(route('dashboard.files.reports', $report))->assertOk();
        $this->assertDatabaseMissing('reports', ['file_path' => 'private.pdf']);
    }

    public function test_super_admin_report_upload_validates_file_types_and_stores_files_privately(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $business = Business::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('super-admin.reports.store'), [
                'business_id' => $business->id,
                'title' => 'Quarterly report',
                'reporting_period' => '2026-Q3',
                'status' => 'draft',
                'visibility' => 'private',
                'file' => UploadedFile::fake()->create('quarterly.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        $report = Report::query()->where('title', 'Quarterly report')->firstOrFail();
        $this->assertSame('local', $report->file_disk);
        $this->assertTrue(Storage::disk('local')->exists($report->file_path));

        $this->postJson(route('super-admin.reports.store'), [
            'business_id' => $business->id,
            'title' => 'Invalid report',
            'status' => 'draft',
            'visibility' => 'private',
            'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
        ])->assertUnprocessable();
    }

    public function test_csv_exports_are_admin_only_and_escape_spreadsheet_formulas(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Business::factory()->create([
            'company_name' => '=HYPERLINK("https://invalid.example")',
            'contact_email' => 'formula@example.test',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('super-admin.exports', 'businesses'))
            ->assertForbidden();

        $response = $this->actingAs($admin)
            ->post(route('super-admin.exports', 'businesses'));
        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            "'=HYPERLINK",
            $response->streamedContent()
        );
    }

    public function test_marketing_and_service_order_updates_preserve_manual_fulfillment_and_price_snapshots(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $business = Business::factory()->create();

        $activity = $this->actingAs($admin)->postJson(route('super-admin.marketing.store'), [
            'business_id' => $business->id,
            'title' => 'Summer promotion',
            'promotion_type' => 'social post',
            'platform' => 'Facebook',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'status' => 'scheduled',
            'caption' => 'Visit our store.',
        ])->assertCreated()->json();

        $this->putJson(route('super-admin.marketing.update', $activity['id']), [
            'business_id' => $business->id,
            'title' => 'Summer promotion',
            'promotion_type' => 'social post',
            'platform' => 'Facebook',
            'status' => 'completed',
            'completed_at' => now()->toDateTimeString(),
            'published_url' => 'https://example.test/post/1',
        ])->assertOk()->assertJsonPath('status', 'completed');

        $serviceResponse = $this->postJson(route('super-admin.services.store'), [
            'name' => 'Photography session',
            'description' => 'A one-hour session.',
            'price' => '125.00',
            'currency' => 'USD',
            'status' => 'available',
        ])->assertCreated()->json();
        $service = Service::query()->findOrFail($serviceResponse['id']);
        $order = Order::factory()->create([
            'business_id' => $business->id,
            'user_id' => $admin->id,
            'status' => 'submitted',
        ]);
        $item = OrderItem::createFromService($order, $service, 1);

        $this->putJson(route('super-admin.services.update', $service), [
            'name' => 'Roanoke photography',
            'description' => 'Updated description.',
            'price' => '175.00',
            'currency' => 'USD',
            'status' => 'available',
        ])->assertOk();

        $this->patchJson(route('super-admin.orders.fulfillment', $order), [
            'status' => 'in_progress',
            'assigned_staff_id' => $admin->id,
            'fulfillment_notes' => 'Contacted member to schedule.',
        ])->assertOk()->assertJsonPath('status', 'in_progress');

        $this->assertSame('125.00', $item->fresh()->unit_price);
        $this->assertSame('175.00', $service->fresh()->price);
        $this->assertSame('Contacted member to schedule.', $order->fresh()->fulfillment_notes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'service.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.fulfillment_updated']);
    }

    public function test_member_announcement_drafting_and_email_preferences_do_not_claim_unconfigured_delivery(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = User::factory()->create();
        $business = Business::factory()->create(['settings' => ['category' => 'Retail']]);
        $this->attachMember($business, $member);

        $this->actingAs($member)
            ->putJson(route('dashboard.email-preferences'), ['email_announcements_opt_in' => true])
            ->assertOk()
            ->assertJsonPath('email_announcements_opt_in', true);

        $announcement = $this->actingAs($admin)
            ->postJson(route('super-admin.announcements.store'), [
                'subject' => 'Local update',
                'body' => 'News from the initiative.',
                'audience' => ['business_category' => 'Retail'],
            ])
            ->assertCreated()
            ->json();

        $this->postJson(route('super-admin.announcements.preview'), ['business_category' => 'Retail'])
            ->assertOk()
            ->assertJsonPath('recipient_count', 1);

        config(['mail.default' => 'array', 'mail.mailers.array.transport' => 'array']);
        $this->postJson(route('super-admin.announcements.send', $announcement['id']))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Email delivery is not configured with a sending transport. The announcement remains a draft.');
        $this->assertDatabaseHas('member_announcements', ['id' => $announcement['id'], 'status' => 'draft']);
    }

    private function businessData(array $overrides = []): array
    {
        return array_replace([
            'company_name' => 'Roanoke Bakery',
            'category' => 'Food and Drink',
            'contact_email' => 'contact@bakery.example',
            'country' => 'US',
        ], $overrides);
    }

    private function attachMember(Business $business, User $user): void
    {
        $business->users()->attach($user->id, [
            'role' => 'owner',
            'access_level' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);
    }
}
