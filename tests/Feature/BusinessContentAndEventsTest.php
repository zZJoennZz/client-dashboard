<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MarketingActivity;
use App\Models\Report;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessContentAndEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_are_linked_to_a_business_and_creator(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();

        $report = Report::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'visibility' => 'private',
        ]);

        $this->assertSame($business->id, $report->business->id);
        $this->assertSame($user->id, $report->creator->id);
        $this->assertSame('private', $report->visibility);
    }

    public function test_resources_are_distinct_from_member_only_content(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();

        $publicResource = Resource::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'visibility' => 'public',
        ]);

        $memberResource = Resource::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'visibility' => 'member',
        ]);

        $this->assertSame('public', $publicResource->visibility);
        $this->assertSame('member', $memberResource->visibility);
        $this->assertSame($business->id, $publicResource->business->id);
    }

    public function test_marketing_activity_belongs_to_business_and_admin(): void
    {
        $business = Business::factory()->create();
        $admin = User::factory()->create();
        $creator = User::factory()->create();

        $activity = MarketingActivity::factory()->create([
            'business_id' => $business->id,
            'responsible_admin_id' => $admin->id,
            'created_by' => $creator->id,
            'status' => 'scheduled',
        ]);

        $this->assertSame($business->id, $activity->business->id);
        $this->assertSame($admin->id, $activity->responsibleAdmin->id);
        $this->assertSame($creator->id, $activity->creator->id);
    }

    public function test_event_date_range_must_be_sensible(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();

        $event = Event::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->subHour(),
        ]);

        $this->assertFalse($event->hasValidDateRange());
    }

    public function test_event_registration_rejects_duplicate_active_registration(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();

        EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    }
}
