<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Resource;
use App\Services\MemberActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberFileController extends Controller
{
    public function resource(Request $request, Resource $resource): StreamedResponse
    {
        abort_if($resource->trashed() || $resource->status !== 'published', 404);
        abort_unless(in_array($resource->visibility, ['member', 'public'], true), 404);

        if ($resource->business_id !== null) {
            abort_unless(
                $request->user()->businesses()
                    ->whereKey($resource->business_id)
                    ->wherePivot('status', 'active')
                    ->exists(),
                404
            );
        }

        abort_if(! $resource->file_path || ! Storage::disk('local')->exists($resource->file_path), 404);
        $businessId = $resource->business_id ?? $request->user()->businesses()
            ->wherePivot('status', 'active')
            ->orderByPivot('is_primary', 'desc')
            ->value('clients.id');
        app(MemberActivityRecorder::class)->record(
            $request->user(),
            'resource_download',
            $businessId,
            'resource',
            $resource->id,
            $resource->title,
            deduplicateDaily: true,
        );

        return Storage::disk('local')->download($resource->file_path, $resource->file_name ?? 'resource');
    }

    public function report(Request $request, Report $report): StreamedResponse
    {
        abort_if($report->trashed() || $report->status !== 'published', 404);
        abort_unless(in_array($report->visibility, ['member', 'public'], true), 404);
        abort_unless(
            $request->user()->businesses()
                ->whereKey($report->business_id)
                ->wherePivot('status', 'active')
                ->exists(),
            404
        );
        abort_if(! $report->file_path || ! Storage::disk($report->file_disk)->exists($report->file_path), 404);
        app(MemberActivityRecorder::class)->record(
            $request->user(),
            'report_download',
            $report->business_id,
            'report',
            $report->id,
            $report->title,
            deduplicateDaily: true,
        );

        return Storage::disk($report->file_disk)->download($report->file_path, $report->file_name ?? 'report');
    }
}
