<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_submit_a_report_to_an_authorized_business(): void
    {
        Storage::fake('local');
        [$member, $business] = $this->memberWithBusiness();

        $response = $this->actingAs($member)->postJson(route('dashboard.reports.submissions.store'), [
            'title' => 'Quarterly impact report',
            'description' => 'Membership outcomes and service activity.',
            'reporting_period_start' => '2026-10-01',
            'reporting_period_end' => '2026-12-31',
            'file' => UploadedFile::fake()->create('impact.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()->assertJsonPath('status', 'pending_review');

        $report = Report::query()->where('title', 'Quarterly impact report')->firstOrFail();
        $this->assertSame($member->id, $report->created_by);
        $this->assertSame($business->id, $report->business_id);
        $this->assertSame('pending_review', $report->status);
        $this->assertSame('2026-10-01 to 2026-12-31', $report->reporting_period);
        $this->assertNotNull($report->submitted_at);
        Storage::disk('local')->assertExists($report->file_path);
    }

    public function test_member_report_submission_validates_required_fields_and_supported_formats(): void
    {
        Storage::fake('local');
        [$member, $business] = $this->memberWithBusiness();

        $this->actingAs($member)->postJson(route('dashboard.reports.submissions.store'), [
            'reporting_period_start' => '2026-10-01',
            'reporting_period_end' => '2026-12-31',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'file']);

        $this->postJson(route('dashboard.reports.submissions.store'), [
            'title' => 'Unsupported file',
            'reporting_period_start' => '2026-10-01',
            'reporting_period_end' => '2026-12-31',
            'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['file']);

        foreach (['pdf', 'csv', 'xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx', 'txt'] as $extension) {
            $mime = match ($extension) {
                'pdf' => 'application/pdf',
                'csv', 'txt' => 'text/plain',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                default => 'application/octet-stream',
            };
            $response = $this->postJson(route('dashboard.reports.submissions.store'), [
                'title' => "Supported {$extension}",
                'reporting_period_start' => '2026-10-01',
                'reporting_period_end' => '2026-12-31',
                'file' => UploadedFile::fake()->create("report.{$extension}", 10, $mime),
            ]);
            $this->assertTrue($response->isSuccessful(), "{$extension}: ".json_encode($response->json()));
        }
    }

    public function test_member_cannot_submit_for_or_download_another_members_business(): void
    {
        Storage::fake('local');
        [$member, $authorizedBusiness] = $this->memberWithBusiness();
        $otherBusiness = Business::factory()->create();

        $this->actingAs($member)->postJson(route('dashboard.reports.submissions.store'), [
            'business_id' => $otherBusiness->id,
            'title' => 'Unauthorized report',
            'reporting_period_start' => '2026-10-01',
            'reporting_period_end' => '2026-12-31',
            'file' => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $submitted = Report::query()->where('title', 'Unauthorized report')->firstOrFail();
        $this->assertSame($authorizedBusiness->id, $submitted->business_id);

        $report = Report::factory()->create([
            'business_id' => $authorizedBusiness->id,
            'created_by' => User::factory(),
            'status' => 'pending_review',
            'file_disk' => 'local',
        ]);

        Storage::disk('local')->put($report->file_path, 'private report');
        $this->actingAs($member)->get(route('dashboard.reports.submissions.download', $report))->assertNotFound();
    }

    public function test_member_submission_rejects_invalid_date_ranges_and_missing_business(): void
    {
        Storage::fake('local');
        [$member, $business] = $this->memberWithBusiness();

        $this->actingAs($member)->postJson(route('dashboard.reports.submissions.store'), [
            'title' => 'Invalid range',
            'reporting_period_start' => '2026-12-31',
            'reporting_period_end' => '2026-10-01',
            'file' => UploadedFile::fake()->create('impact.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['reporting_period_end']);

        $memberWithoutBusiness = User::factory()->create();
        $this->actingAs($memberWithoutBusiness)->postJson(route('dashboard.reports.submissions.store'), [
            'title' => 'No business',
            'reporting_period_start' => '2026-10-01',
            'reporting_period_end' => '2026-12-31',
            'file' => UploadedFile::fake()->create('impact.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable();
    }

    public function test_admin_can_reject_or_publish_a_submitted_report(): void
    {
        Storage::fake('local');
        [$member, $business] = $this->memberWithBusiness();
        $admin = User::factory()->superAdmin()->create();
        $submission = Report::factory()->create([
            'business_id' => $business->id,
            'created_by' => $member->id,
            'status' => 'pending_review',
            'visibility' => 'private',
            'submitted_at' => now(),
        ]);

        $this->actingAs($member)->patchJson(route('super-admin.reports.review', $submission), [
            'decision' => 'reject',
            'review_feedback' => 'Please add the reporting period.',
        ])->assertForbidden();

        $this->actingAs($admin)->patchJson(route('super-admin.reports.review', $submission), [
            'decision' => 'reject',
            'review_feedback' => 'Please add the reporting period.',
        ])->assertOk();
        $submission->refresh();
        $this->assertSame('rejected', $submission->status);
        $this->assertSame('Please add the reporting period.', $submission->review_feedback);
        $this->assertSame($admin->id, $submission->reviewed_by);

        $this->actingAs($admin)->patchJson(route('super-admin.reports.review', $submission), [
            'decision' => 'approve',
            'visibility' => 'member',
        ])->assertOk();
        $submission->refresh();
        $this->assertSame('published', $submission->status);
        $this->assertSame('member', $submission->visibility);
        $this->assertNotNull($submission->published_at);

        $this->actingAs($member)->getJson(route('dashboard.data'))->assertOk()
            ->assertJsonFragment(['id' => $submission->id, 'title' => $submission->title])
            ->assertJsonPath('report_submissions.0.id', $submission->id);
    }

    public function test_pending_and_rejected_reports_are_not_in_the_published_library(): void
    {
        [$member, $business] = $this->memberWithBusiness();

        Report::factory()->create([
            'business_id' => $business->id,
            'created_by' => $member->id,
            'status' => 'pending_review',
            'visibility' => 'member',
        ]);
        Report::factory()->create([
            'business_id' => $business->id,
            'created_by' => $member->id,
            'status' => 'rejected',
            'visibility' => 'member',
        ]);

        $this->actingAs($member)->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonCount(0, 'reports')
            ->assertJsonCount(2, 'report_submissions');
    }

    public function test_member_can_privately_download_their_own_submission(): void
    {
        Storage::fake('local');
        [$member, $business] = $this->memberWithBusiness();
        $report = Report::factory()->create([
            'business_id' => $business->id,
            'created_by' => $member->id,
            'status' => 'pending_review',
            'visibility' => 'private',
        ]);
        Storage::disk('local')->put($report->file_path, 'private report');

        $this->actingAs($member)
            ->get(route('dashboard.reports.submissions.download', $report))
            ->assertOk();
    }

    public function test_private_submission_downloads_are_limited_to_uploader_business_admin_and_super_admin(): void
    {
        Storage::fake('local');
        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();
        $uploader = User::factory()->create();
        $peer = User::factory()->create();
        $businessAdmin = User::factory()->create();
        $otherBusinessAdmin = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->attachBusinessUser($business, $uploader, 'member');
        $this->attachBusinessUser($business, $peer, 'member');
        $this->attachBusinessUser($business, $businessAdmin, 'admin');
        $this->attachBusinessUser($otherBusiness, $otherBusinessAdmin, 'admin');

        $path = 'member/reports/private-access.pdf';
        Storage::disk('local')->put($path, 'private report');
        $report = Report::factory()->create([
            'business_id' => $business->id,
            'created_by' => $uploader->id,
            'file_path' => $path,
            'file_disk' => 'local',
            'status' => 'pending_review',
            'visibility' => 'private',
        ]);

        $this->actingAs($peer)
            ->get(route('dashboard.reports.submissions.download', $report))
            ->assertNotFound();
        $this->actingAs($otherBusinessAdmin)
            ->get(route('dashboard.reports.submissions.download', $report))
            ->assertNotFound();
        $this->actingAs($uploader)
            ->get(route('dashboard.reports.submissions.download', $report))
            ->assertOk();
        $this->actingAs($businessAdmin)
            ->get(route('dashboard.reports.submissions.download', $report))
            ->assertOk();
        $this->actingAs($superAdmin)
            ->get(route('dashboard.reports.submissions.download', $report))
            ->assertOk();
    }

    private function attachBusinessUser(Business $business, User $user, string $role): void
    {
        $business->users()->attach($user->id, [
            'role' => $role,
            'access_level' => $role,
            'status' => 'active',
            'is_primary' => false,
        ]);
    }

    private function memberWithBusiness(): array
    {
        $member = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($member->id, [
            'role' => 'member',
            'access_level' => 'member',
            'status' => 'active',
            'is_primary' => true,
        ]);

        return [$member, $business];
    }
}
