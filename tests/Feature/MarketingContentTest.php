<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\MarketingActivity;
use App\Models\Resource;
use App\Models\ResourceAcknowledgment;
use App\Models\ResourceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketingContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_the_shared_marketing_schedule_to_every_member(): void
    {
        $member = User::factory()->create();
        $business = Business::factory()->create();
        $this->attachMember($business, $member);

        MarketingActivity::factory()->create([
            'title' => 'Shared social post',
            'promotion_type' => 'Social Media Post',
            'platforms' => [['platform' => 'Facebook', 'url' => 'https://example.test/post']],
            'scheduled_at' => now()->addDays(8),
            'status' => 'scheduled',
        ]);

        $this->actingAs($member)->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('marketing_activities.0.title', 'Shared social post')
            ->assertJsonPath('marketing_activities.0.platforms.0.platform', 'Facebook')
            ->assertJsonPath('marketing_activities.0.platforms.0.url', 'https://example.test/post')
            ->assertJsonPath('marketing_options.promotion_types.0', 'Social Media Post')
            ->assertJsonMissingPath('marketing_activities.0.business_id')
            ->assertJsonMissingPath('marketing_activities.0.scope')
            ->assertJsonMissingPath('highlights');
    }

    public function test_marketing_activity_schema_has_no_business_scope_or_provider_assignment(): void
    {
        $this->assertFalse(Schema::hasColumn('marketing_activities', 'business_id'));
        $this->assertFalse(Schema::hasColumn('marketing_activities', 'scope'));
        $this->assertFalse(Schema::hasColumn('marketing_activities', 'responsible_admin_id'));
        $this->assertTrue(Schema::hasColumn('marketing_activities', 'platforms'));
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

    public function test_super_admin_can_create_duplicate_cancel_and_archive_global_activities(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $response = $this->actingAs($admin)->postJson(route('super-admin.marketing.store'), [
            'title' => 'Monthly member newsletter',
            'promotion_type' => 'Email Newsletter',
            'platforms' => [
                ['platform' => 'Facebook', 'url' => 'https://example.test/newsletter'],
                ['platform' => 'Instagram', 'url' => null],
            ],
            'scheduled_at' => now()->addMonth()->toDateTimeString(),
            'status' => 'scheduled',
        ])->assertCreated();
        $activity = MarketingActivity::query()->findOrFail($response->json('id'));
        $this->assertSame([
            ['platform' => 'Facebook', 'url' => 'https://example.test/newsletter'],
            ['platform' => 'Instagram', 'url' => null],
        ], $activity->platforms);

        $this->getJson(route('super-admin.workspace', 'marketing'))
            ->assertOk()
            ->assertJsonPath('data.0.platforms.0.url', 'https://example.test/newsletter')
            ->assertJsonPath('marketing_options.promotion_types.0', 'Social Media Post')
            ->assertJsonPath('business_options', [])
            ->assertJsonMissingPath('data.0.business_name')
            ->assertJsonMissingPath('data.0.scope');
        $this->postJson(route('super-admin.exports', 'marketing'), ['business_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business_id']);

        $duplicate = $this->postJson(route('super-admin.marketing.duplicate', $activity))
            ->assertCreated()->json();
        $copy = MarketingActivity::query()->findOrFail($duplicate['id']);
        $this->assertSame('planned', $copy->status);
        $this->assertSame($activity->platforms, $copy->platforms);

        $this->patchJson(route('super-admin.marketing.status', $copy), ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('status', 'cancelled');
        $this->deleteJson(route('super-admin.marketing.archive', $copy))->assertNoContent();
        $this->assertSoftDeleted('marketing_activities', ['id' => $copy->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing.duplicated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing.status_updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing.archived']);
    }

    public function test_marketing_activity_validates_prefilled_promotion_and_platform_options(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->postJson(route('super-admin.marketing.store'), [
            'title' => 'Invalid promotion',
            'promotion_type' => 'Unlisted promotion',
            'platforms' => [['platform' => 'Unlisted platform', 'url' => '']],
            'status' => 'planned',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['promotion_type', 'platforms.0.platform']);
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