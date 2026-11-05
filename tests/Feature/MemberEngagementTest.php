<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberActivity;
use App\Models\Report;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberEngagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_login_updates_last_login_and_records_a_minimal_activity(): void
    {
        $member = User::factory()->create([
            'email' => 'login-member@example.com',
            'password' => 'StrongPassword123',
        ]);
        $business = Business::factory()->create();
        $business->users()->attach($member->id, [
            'role' => 'owner',
            'access_level' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => 'login-member@example.com',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertNotNull($member->fresh()->last_login_at);
        $this->assertDatabaseHas('member_activities', [
            'user_id' => $member->id,
            'business_id' => $business->id,
            'activity_type' => 'login',
            'subject_label' => null,
            'metadata' => null,
        ]);

        EventFacade::dispatch(new Login('web', $member->fresh(), false));
        $this->assertDatabaseCount('member_activities', 1);

        $admin = User::factory()->superAdmin()->create();
        EventFacade::dispatch(new Login('web', $admin, false));
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertDatabaseMissing('member_activities', ['user_id' => $admin->id, 'activity_type' => 'login']);
    }

    public function test_rsvp_cancellation_and_member_dashboard_history_are_scoped_and_distinguish_attendance(): void
    {
        $member = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($member->id, [
            'role' => 'member',
            'access_level' => 'member',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $event = Event::factory()->create([
            'business_id' => $business->id,
            'registration_enabled' => true,
            'is_published' => true,
            'status' => 'published',
        ]);
        $otherBusiness = Business::factory()->create();
        $otherMember = User::factory()->create();
        $privateEvent = Event::factory()->create(['business_id' => $otherBusiness->id]);
        MemberActivity::query()->create([
            'user_id' => $otherMember->id,
            'business_id' => $otherBusiness->id,
            'event_id' => $privateEvent->id,
            'activity_type' => 'event_rsvp',
            'subject_label' => 'Private event',
        ]);

        $this->actingAs($member)
            ->postJson(route('dashboard.events.register', $event))
            ->assertCreated()
            ->assertJsonPath('status', 'registered');
        $this->assertDatabaseCount('member_activities', 2);

        $this->postJson(route('dashboard.events.register', $event))->assertUnprocessable();
        $this->assertDatabaseCount('member_activities', 2);

        $this->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('engagement.rsvps_30_days', 1)
            ->assertJsonPath('engagement.recorded_attendance', 0)
            ->assertJsonPath('participation.0.event', $event->title)
            ->assertJsonMissing(['subject_label' => 'Private event']);

        $registration = EventRegistration::query()->where('event_id', $event->id)->firstOrFail();
        $this->deleteJson(route('dashboard.events.cancel', $registration))
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
        $this->assertDatabaseHas('member_activities', [
            'user_id' => $member->id,
            'activity_type' => 'event_cancelled',
            'event_id' => $event->id,
        ]);
        $this->assertDatabaseMissing('member_activities', [
            'user_id' => $member->id,
            'activity_type' => 'event_attended',
        ]);
    }

    public function test_attendance_is_reversible_and_historical_activity_is_retained(): void
    {
        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();
        $member = User::factory()->create();
        $business->users()->attach($member->id, [
            'role' => 'member',
            'access_level' => 'member',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $event = Event::factory()->create(['business_id' => $business->id]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'registered',
            'attended_at' => null,
        ]);

        $regular = User::factory()->create();
        $otherBusinessOwner = User::factory()->create();
        $otherBusiness->users()->attach($otherBusinessOwner, [
            'role' => 'owner',
            'access_level' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->actingAs($regular)
            ->patchJson(route('super-admin.event-registrations.update', $registration), ['status' => 'attended'])
            ->assertForbidden();
        $this->actingAs($otherBusinessOwner)
            ->patchJson(route('super-admin.event-registrations.update', $registration), ['status' => 'attended'])
            ->assertForbidden();
        $this->assertDatabaseMissing('member_activities', ['activity_type' => 'event_attended']);

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)
            ->patchJson(route('super-admin.event-registrations.update', $registration), ['status' => 'attended'])
            ->assertOk();

        $attendedAt = $registration->fresh()->attended_at;
        $this->assertNotNull($attendedAt);
        $this->assertSame('attended', $registration->fresh()->status);

        $this->patchJson(route('super-admin.event-registrations.update', $registration), ['status' => 'attended'])
            ->assertOk();
        $this->assertDatabaseCount('member_activities', 1);
        $this->assertDatabaseHas('member_activities', [
            'user_id' => $member->id,
            'business_id' => $business->id,
            'event_id' => $event->id,
            'activity_type' => 'event_attended',
        ]);
        $this->assertTrue($attendedAt->equalTo($registration->fresh()->attended_at));

        $this->getJson(route('super-admin.engagement', ['business_id' => $business->id]))
            ->assertOk()
            ->assertJsonPath('metrics.recorded_attendance', 1)
            ->assertJsonPath('activities.data.0.type', 'event_attended')
            ->assertJsonPath('participation.data.0.status', 'attended')
            ->assertJsonPath('business_summaries.data.0.business', $business->company_name)
            ->assertJsonPath('participation.data.0.member', $member->profile->full_name)
            ->assertJsonPath('event_totals.data.0.registrations', 1)
            ->assertJsonPath('event_totals.data.0.attendance_recorded', 1);

        $this->actingAs($member)
            ->getJson(route('dashboard.data'))
            ->assertJsonPath('attendance_history.0.event', $event->title)
            ->assertJsonPath('engagement.recorded_attendance', 1);

        $this->actingAs($admin)
            ->patchJson(route('super-admin.event-registrations.update', $registration), ['status' => 'registered'])
            ->assertOk();
        $this->assertNull($registration->fresh()->attended_at);
        $this->assertDatabaseCount('member_activities', 1);

        $this->actingAs($member)
            ->getJson(route('dashboard.data'))
            ->assertJsonPath('engagement.recorded_attendance', 0)
            ->assertJsonPath('attendance_history.0.event', $event->title);

        $this->actingAs($admin)
            ->patchJson(route('super-admin.event-registrations.update', $registration), ['status' => 'attended'])
            ->assertOk();
        $this->assertNotNull($registration->fresh()->attended_at);
        $this->assertDatabaseCount('member_activities', 2);

        $this->actingAs($admin)
            ->patchJson(route('super-admin.event-registrations.update', $registration), ['status' => 'cancelled'])
            ->assertOk();
        $this->assertSame('cancelled', $registration->fresh()->status);
        $this->assertNull($registration->fresh()->attended_at);
        $this->assertDatabaseCount('member_activities', 2);

        $this->getJson(route('super-admin.engagement', ['business_id' => $business->id]))
            ->assertOk()
            ->assertJsonPath('metrics.recorded_attendance', 0)
            ->assertJsonPath('activities.data.0.type', 'event_attended')
            ->assertJsonPath('participation.data.0.status', 'cancelled');

        $export = $this->actingAs($admin)
            ->post(route('super-admin.exports', ['type' => 'registrants']), ['status' => 'attended']);
        $export->assertOk();
        $this->assertStringNotContainsString($member->email, $export->streamedContent());
    }

    public function test_provider_engagement_summaries_and_participation_filters_are_accurate(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $business = Business::factory()->create(['company_name' => 'Filterable Business']);
        $member = User::factory()->create();
        $event = Event::factory()->create([
            'business_id' => $business->id,
            'title' => 'Filtered Community Event',
            'starts_at' => now()->subDays(2),
        ]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'attended',
            'registered_at' => now()->subDays(3),
            'attended_at' => now()->subDays(2),
        ]);
        MemberActivity::query()->create([
            'user_id' => $member->id,
            'business_id' => $business->id,
            'activity_type' => 'login',
            'subject_label' => null,
        ]);
        MemberActivity::query()->create([
            'user_id' => $member->id,
            'business_id' => $business->id,
            'activity_type' => 'report_viewed',
            'subject_label' => 'Quarterly report',
        ]);

        $this->actingAs($admin)
            ->getJson(route('super-admin.engagement', [
                'business_id' => $business->id,
                'event_id' => $event->id,
                'registration_status' => 'attended',
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
                'summary_search' => 'Filterable',
            ]))
            ->assertOk()
            ->assertJsonPath('business_summaries.meta.total', 1)
            ->assertJsonPath('business_summaries.data.0.logins_30_days', 1)
            ->assertJsonPath('business_summaries.data.0.reports_viewed_30_days', 1)
            ->assertJsonPath('event_totals.meta.total', 1)
            ->assertJsonPath('event_totals.data.0.registrations', 1)
            ->assertJsonPath('event_totals.data.0.attendance_recorded', 1)
            ->assertJsonPath('participation.meta.total', 1)
            ->assertJsonPath('participation.data.0.id', $registration->id);

        $this->actingAs($admin)
            ->getJson(route('super-admin.engagement', [
                'event_id' => $event->id,
                'registration_status' => 'cancelled',
            ]))
            ->assertOk()
            ->assertJsonPath('participation.meta.total', 0);
    }

    public function test_engagement_endpoint_is_admin_only_and_report_download_tracking_follows_access_checks(): void
    {
        $this->getJson(route('super-admin.engagement'))->assertUnauthorized();
        $member = User::factory()->create();
        $this->actingAs($member)->getJson(route('super-admin.engagement'))->assertForbidden();

        Storage::fake('local');
        Storage::disk('local')->put('reports/member-report.pdf', 'report');
        $business = Business::factory()->create();
        $business->users()->attach($member->id, [
            'role' => 'member',
            'access_level' => 'member',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $outsider = User::factory()->create();
        $report = Report::factory()->create([
            'business_id' => $business->id,
            'title' => 'Member report',
            'file_path' => 'reports/member-report.pdf',
            'file_disk' => 'local',
            'status' => 'published',
            'visibility' => 'member',
        ]);

        $this->actingAs($outsider)->get(route('dashboard.files.reports', $report))->assertNotFound();
        $this->assertDatabaseMissing('member_activities', ['activity_type' => 'report_download']);
        $this->actingAs($member)->get(route('dashboard.files.reports', $report))->assertOk();
        $this->assertDatabaseHas('member_activities', [
            'user_id' => $member->id,
            'business_id' => $business->id,
            'activity_type' => 'report_download',
            'subject_label' => 'Member report',
        ]);
    }

    public function test_report_view_and_download_are_daily_deduplicated_and_attributed_to_the_business(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('reports/daily-report.pdf', 'report');
        $member = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($member->id, [
            'role' => 'member',
            'access_level' => 'member',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $report = Report::factory()->create([
            'business_id' => $business->id,
            'title' => 'Daily report',
            'file_path' => 'reports/daily-report.pdf',
            'file_disk' => 'local',
            'status' => 'published',
            'visibility' => 'member',
        ]);

        $this->postJson(route('dashboard.activity.reports.viewed', $report))->assertUnauthorized();

        $this->actingAs($member);
        $this->postJson(route('dashboard.activity.reports.viewed', $report))->assertOk();
        $this->postJson(route('dashboard.activity.reports.viewed', $report))->assertOk();
        $this->get(route('dashboard.files.reports', $report))->assertOk();
        $this->get(route('dashboard.files.reports', $report))->assertOk();

        $this->assertDatabaseCount('member_activities', 2);
        $this->assertDatabaseHas('member_activities', [
            'user_id' => $member->id,
            'business_id' => $business->id,
            'activity_type' => 'report_viewed',
            'subject_type' => 'report',
            'subject_id' => $report->id,
            'metadata' => null,
        ]);
        $this->assertSame(now()->toDateString(), MemberActivity::query()->where('activity_type', 'report_viewed')->firstOrFail()->activity_date->toDateString());
        $this->assertDatabaseHas('member_activities', [
            'user_id' => $member->id,
            'business_id' => $business->id,
            'activity_type' => 'report_download',
            'subject_type' => 'report',
            'subject_id' => $report->id,
        ]);

        $this->travel(1)->day();
        $this->postJson(route('dashboard.activity.reports.viewed', $report))->assertOk();
        $this->get(route('dashboard.files.reports', $report))->assertOk();
        $this->assertDatabaseCount('member_activities', 4);
    }

    public function test_report_view_tracking_rejects_inaccessible_reports(): void
    {
        $member = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($member->id, [
            'role' => 'member',
            'access_level' => 'member',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $outsider = User::factory()->create();
        $draftReport = Report::factory()->create([
            'business_id' => $business->id,
            'status' => 'draft',
            'visibility' => 'member',
        ]);
        $privateReport = Report::factory()->create([
            'business_id' => $business->id,
            'status' => 'published',
            'visibility' => 'private',
        ]);
        $inaccessibleReport = Report::factory()->create([
            'business_id' => $business->id,
            'status' => 'published',
            'visibility' => 'member',
        ]);

        $this->actingAs($member)
            ->postJson(route('dashboard.activity.reports.viewed', $draftReport))
            ->assertNotFound();
        $this->actingAs($member)
            ->postJson(route('dashboard.activity.reports.viewed', $privateReport))
            ->assertNotFound();
        $this->actingAs($outsider)
            ->postJson(route('dashboard.activity.reports.viewed', $inaccessibleReport))
            ->assertNotFound();

        $this->assertDatabaseMissing('member_activities', [
            'activity_type' => 'report_viewed',
        ]);
    }

    public function test_shared_resource_activity_is_attributed_to_an_authorized_business(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('shared-resource.pdf', 'resource');
        $member = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($member->id, [
            'role' => 'owner',
            'access_level' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $resource = Resource::factory()->create([
            'business_id' => null,
            'file_path' => 'shared-resource.pdf',
            'status' => 'published',
            'visibility' => 'member',
        ]);

        $this->actingAs($member)
            ->postJson(route('dashboard.activity.resources.viewed', $resource))
            ->assertOk();
        $this->get(route('dashboard.files.resources', $resource))->assertOk();

        $this->assertDatabaseHas('member_activities', [
            'user_id' => $member->id,
            'business_id' => $business->id,
            'activity_type' => 'resource_viewed',
            'subject_type' => 'resource',
            'subject_id' => $resource->id,
        ]);
        $this->assertDatabaseHas('member_activities', [
            'user_id' => $member->id,
            'business_id' => $business->id,
            'activity_type' => 'resource_download',
            'subject_type' => 'resource',
            'subject_id' => $resource->id,
        ]);
    }
}
