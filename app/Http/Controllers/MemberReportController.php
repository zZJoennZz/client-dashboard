<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberReportController extends Controller
{
    private const FILE_TYPES = 'pdf,csv,xls,xlsx,doc,docx,ppt,pptx,txt';

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'reporting_period_start' => ['required', 'date_format:Y-m-d'],
            'reporting_period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:reporting_period_start'],
            'file' => ['required', 'file', 'extensions:'.self::FILE_TYPES, 'mimes:'.self::FILE_TYPES, 'max:20480'],
        ]);

        $business = $request->user()->businesses()
            ->wherePivot('status', 'active')
            ->orderByPivot('is_primary', 'desc')
            ->first();
        abort_unless($business, 422);

        $file = $request->file('file');
        $report = DB::transaction(function () use ($request, $data, $file, $business): Report {
            return Report::query()->create([
                'business_id' => $business->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'reporting_period' => "{$data['reporting_period_start']} to {$data['reporting_period_end']}",
                'reporting_period_start' => $data['reporting_period_start'],
                'reporting_period_end' => $data['reporting_period_end'],
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $file->storeAs('member/reports', Str::uuid().'.'.$file->extension(), 'local'),
                'file_disk' => 'local',
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'pending_review',
                'visibility' => 'private',
                'created_by' => $request->user()->id,
                'submitted_at' => now(),
            ]);
        });

        return response()->json([
            'id' => $report->id,
            'status' => $report->status,
        ], 201);
    }

    public function download(Request $request, Report $report): StreamedResponse
    {
        abort_unless($report->created_by === $request->user()->id, 404);
        abort_unless(
            $request->user()->businesses()
                ->whereKey($report->business_id)
                ->wherePivot('status', 'active')
                ->exists(),
            404
        );
        abort_if(! $report->file_path || ! Storage::disk($report->file_disk)->exists($report->file_path), 404);

        return Storage::disk($report->file_disk)->download($report->file_path, $report->file_name ?? 'report');
    }
}
