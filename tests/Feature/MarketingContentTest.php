<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\MarketingActivity;
use App\Models\Resource;
use App\Models\ResourceAcknowledgment;
use App\Models\ResourceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_only_associated_business_highlights_and_shared_program_activities(): void
    {
        $member = User::factory()->create();
        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();
        $this->attachMember($business, $member);

        MarketingActivity::factory()->create([
            'business_id' => $business->id,
            'scope' => 'business',
            'title' => 'My scheduled highlight',
            'scheduled_at' => now()->addDays(8),
            'status' => 'scheduled',
        ]);
        MarketingActivity::factory()->create([
            'business_id' => $otherBusiness->id,
            'scope' => 'business',
            'title' => 'Private highlight',
        ]);
        MarketingActivity::factory()->create([
            'business_id' => null,
            'scope' => 'program',
            'title' => 'Shared holiday campaign',
        ]);

        $this->actingAs($member)->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('highlights.0.title', 'My scheduled highlight')
            ->assertJsonPath('program_activities.0.title', 'Shared holiday campaign')
            ->assertJsonMissing(['title' => 'Private highlight']);
    }

    public function test_member_can_acknowledge_current_guideline_version_for_their_business_only(): void
    {
        $member = User::factory()->create();
        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();
        $this->attachMember($business, $member);
        $guideline = Resource::factory()->create([
            'business_id' => null,
            'resource_group' => 'guideline',
            'requires_acknowledgment' => true,
            'version' => 3,
            'status' => 'published',
            'visibility' => 'member',
        ]);

        $this->actingAs($member)->postJson(route('dashboard.resources.acknowledge', $guideline))
            ->assertOk()
            ->assertJsonPath('version', 3);
        $this->assertDatabaseHas('resource_acknowledgments', [
            'resource_id' => $guideline->id,
            'business_id' => $business->id,
            'version' => 3,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseMissing('resource_acknowledgments', [
            'resource_id' => $guideline->id,
            'business_id' => $otherBusiness->id,
        ]);
        $this->assertInstanceOf(ResourceAcknowledgment::class, ResourceAcknowledgment::query()->first());
    }

    public function test_super_admin_can_create_duplicate_cancel_and_archive_program_activities(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $response = $this->actingAs($admin)->postJson(route('super-admin.marketing.store'), [
            'scope' => 'program',
            'business_id' => null,
            'title' => 'Monthly member newsletter',
            'promotion_type' => 'Newsletter',
            'scheduled_at' => now()->addMonth()->toDateTimeString(),
            'status' => 'scheduled',
        ])->assertCreated();
        $activity = MarketingActivity::query()->findOrFail($response->json('id'));

        $duplicate = $this->postJson(route('super-admin.marketing.duplicate', $activity))
            ->assertCreated()->json();
        $copy = MarketingActivity::query()->findOrFail($duplicate['id']);
        $this->assertSame('program', $copy->scope);
        $this->assertSame('planned', $copy->status);

        $this->patchJson(route('super-admin.marketing.status', $copy), ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('status', 'cancelled');
        $this->deleteJson(route('super-admin.marketing.archive', $copy))->assertNoContent();
        $this->assertSoftDeleted('marketing_activities', ['id' => $copy->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing.duplicated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing.status_updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing.archived']);
    }

    public function test_resource_edits_preserve_version_history_and_audit_changes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $business = Business::factory()->create();
        $response = $this->actingAs($admin)->postJson(route('super-admin.resources.store'), [
            'title' => 'Social media guide',
            'resource_group' => 'how_to',
            'resource_type' => 'article',
            'category' => 'Social Media',
            'tags' => ['Social Media', 'Promotion'],
            'content' => 'Original article copy.',
            'business_id' => null,
            'status' => 'published',
            'visibility' => 'member',
        ])->assertCreated();
        $resource = Resource::query()->findOrFail($response->json('id'));

        $this->putJson(route('super-admin.resources.update', $resource), [
            'title' => 'Social media guide',
            'resource_group' => 'how_to',
            'resource_type' => 'article',
            'category' => 'Social Media',
            'tags' => ['Social Media', 'Promotion'],
            'content' => 'Updated article copy.',
            'business_id' => $business->id,
            'status' => 'published',
            'visibility' => 'member',
        ])->assertOk();

        $this->assertSame(2, $resource->fresh()->version);
        $this->assertSame(2, ResourceVersion::query()->where('resource_id', $resource->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'resource.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'resource.updated']);
    }

    private function attachMember(Business $business, User $member): void
    {
        $business->users()->attach($member->id, [
            'role' => 'owner',
            'access_level' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);
    }
}