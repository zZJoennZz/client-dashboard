<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BusinessMetricsAndAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_metrics_preserve_multiple_reporting_periods(): void
    {
        $business = Business::factory()->create();

        $olderMetric = BusinessMetric::factory()->create([
            'business_id' => $business->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'value' => '125.5000',
        ]);
        $newerMetric = BusinessMetric::factory()->create([
            'business_id' => $business->id,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'value' => '150.2500',
        ]);

        $this->assertSame($business->id, $olderMetric->business->id);
        $this->assertSame('125.5000', $olderMetric->value);
        $this->assertCount(2, $business->fresh()->metrics);
        $this->assertNotSame($olderMetric->period_start->toDateString(), $newerMetric->period_start->toDateString());
    }

    public function test_audit_log_records_actor_target_and_strips_secret_metadata_recursively(): void
    {
        $actor = User::factory()->create();
        $business = Business::factory()->create();

        $auditLog = AuditLog::record(
            $actor,
            'business.updated',
            $business,
            ['description' => 'Old', 'password' => 'not-retained'],
            ['description' => 'New', 'nested' => ['api_key' => 'not-retained', 'status' => 'active']],
            ['source' => 'admin', 'authorization' => 'not-retained'],
        );

        $this->assertSame($actor->id, $auditLog->actor->id);
        $this->assertSame($business->id, $auditLog->target->id);
        $this->assertSame(['description' => 'Old'], $auditLog->old_values);
        $this->assertSame(['description' => 'New', 'nested' => ['status' => 'active']], $auditLog->new_values);
        $this->assertSame(['source' => 'admin'], $auditLog->context);
        $this->assertArrayNotHasKey('password', $auditLog->old_values);
    }

    public function test_audit_history_requires_the_audit_view_permission(): void
    {
        $unauthorized = User::factory()->create();
        $authorized = User::factory()->create();
        $authorized->givePermissionTo(Permission::findOrCreate('view audit logs', 'web'));

        $this->assertFalse(Gate::forUser($unauthorized)->allows('viewAny', AuditLog::class));
        $this->assertTrue(Gate::forUser($authorized)->allows('viewAny', AuditLog::class));
    }
}
