<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Resource;
use App\Models\User;
use App\Services\MemberActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberActivityController extends Controller
{
    public function reportViewed(Request $request, Report $report, MemberActivityRecorder $recorder): JsonResponse
    {
        abort_if($report->trashed() || $report->status !== 'published', 404);
        abort_unless(in_array($report->visibility, ['member', 'public'], true), 404);
        $this->assertBusinessAccess($request, $report->business_id);

        $recorder->record(
            $request->user(),
            'report_viewed',
            $report->business_id,
            'report',
            $report->id,
            $report->title,
            deduplicateDaily: true,
        );

        return response()->json(['recorded' => true]);
    }

    public function resourceViewed(Request $request, Resource $resource, MemberActivityRecorder $recorder): JsonResponse
    {
        abort_if($resource->trashed() || $resource->status !== 'published', 404);
        abort_unless(in_array($resource->visibility, ['member', 'public'], true), 404);
        if ($resource->business_id !== null) {
            $this->assertBusinessAccess($request, $resource->business_id);
            $businessId = $resource->business_id;
        } else {
            $businessId = $request->user()->businesses()
                ->wherePivot('status', 'active')
                ->orderByPivot('is_primary', 'desc')
                ->value('clients.id');
        }

        $recorder->record(
            $request->user(),
            'resource_viewed',
            $businessId,
            'resource',
            $resource->id,
            $resource->title,
            deduplicateDaily: true,
        );

        return response()->json(['recorded' => true]);
    }

    private function assertBusinessAccess(Request $request, int $businessId): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->businesses()
            ->whereKey($businessId)
            ->wherePivot('status', 'active')
            ->exists(), 404);
    }
}
