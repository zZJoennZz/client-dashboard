<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Report;
use App\Models\Resource;
use App\Models\ResourceVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\Ai\GeminiReportAnalyzer;

class SuperAdminContentController extends Controller
{
    private const FILE_TYPES = 'pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp';

    public function storeResource(Request $request): JsonResponse
    {
        $data = $this->resourceData($request, true);
        $resource = DB::transaction(function () use ($request, $data): Resource {
            $resource = Resource::query()->create($this->resourceAttributes($request, $data) + [
                'created_by' => $request->user()->id,
            ]);
            $resource->versions()->create([
                'version' => $resource->version,
                'snapshot' => $this->resourceSnapshot($resource),
                'created_by' => $request->user()->id,
            ]);
            AuditLog::record($request->user(), 'resource.created', $resource, [], $this->contentAudit($resource));

            return $resource;
        });

        if ($resource->status === 'published' && $resource->requires_acknowledgment) {
            $this->notifyGuidelineBusinesses($resource);
        }

        return response()->json(['id' => $resource->id], 201);
    }

    public function updateResource(Request $request, Resource $resource): JsonResponse
    {
        $data = $this->resourceData($request, false);
        $wasPublishedRequiringAck = $resource->status === 'published' && $resource->requires_acknowledgment;
        $oldVersion = $resource->version;
        DB::transaction(function () use ($request, $data, $resource): void {
            $old = $this->contentAudit($resource);
            if (! $resource->versions()->exists()) {
                $resource->versions()->create([
                    'version' => $resource->version ?? 1,
                    'snapshot' => $this->resourceSnapshot($resource),
                    'created_by' => $resource->created_by,
                ]);
            }
            $resource->fill($this->resourceAttributes($request, $data));
            $newVersion = $resource->isDirty(['title', 'description', 'content', 'resource_type', 'category', 'tags', 'file_name', 'file_path', 'file_url', 'video_embed_url']);
            if ($newVersion) {
                $resource->version++;
            }
            $resource->save();
            if ($newVersion) {
                $resource->versions()->create([
                    'version' => $resource->version,
                    'snapshot' => $this->resourceSnapshot($resource),
                    'created_by' => $request->user()->id,
                ]);
            }
            AuditLog::record($request->user(), 'resource.updated', $resource, $old, $this->contentAudit($resource));
        });

        if ($resource->status === 'published' && $resource->requires_acknowledgment && (! $wasPublishedRequiringAck || $resource->version > $oldVersion)) {
            $this->notifyGuidelineBusinesses($resource);
        }

        return response()->json(['id' => $resource->id]);
    }

    public function archiveResource(Request $request, Resource $resource): Response
    {
        DB::transaction(function () use ($request, $resource): void {
            $resource->delete();
            AuditLog::record($request->user(), 'resource.archived', $resource, [], ['status' => 'archived']);
        });

        return response()->noContent();
    }

    public function storeReport(Request $request): JsonResponse
    {
        $data = $this->reportData($request, true);
        $report = DB::transaction(function () use ($request, $data): Report {
            $report = Report::query()->create($this->reportAttributes($request, $data) + [
                'created_by' => $request->user()->id,
            ]);
            AuditLog::record($request->user(), 'report.created', $report, [], $this->contentAudit($report));

            return $report;
        });

        return response()->json(['id' => $report->id], 201);
    }

    public function updateReport(Request $request, Report $report): JsonResponse
    {
        $data = $this->reportData($request, false);
        $oldPath = $report->file_path;

        DB::transaction(function () use ($request, $data, $report): void {
            $old = $this->contentAudit($report);
            $report->fill($this->reportAttributes($request, $data));
            $report->save();
            AuditLog::record($request->user(), 'report.updated', $report, $old, $this->contentAudit($report));
        });

        if ($report->wasChanged('file_path') && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json(['id' => $report->id]);
    }

    public function archiveReport(Request $request, Report $report): Response
    {
        DB::transaction(function () use ($request, $report): void {
            $report->delete();
            AuditLog::record($request->user(), 'report.archived', $report, [], ['status' => 'archived']);
        });

        return response()->noContent();
    }

    public function downloadResource(Resource $resource): StreamedResponse
    {
        abort_if($resource->trashed() || ! $resource->file_path || ! Storage::disk('local')->exists($resource->file_path), 404);

        return Storage::disk('local')->download($resource->file_path, $resource->file_name ?? 'resource');
    }

    public function downloadResourceVersion(Resource $resource, ResourceVersion $version): StreamedResponse
    {
        abort_unless($version->resource_id === $resource->id, 404);
        $path = data_get($version->snapshot, 'file_path');
        $fileName = data_get($version->snapshot, 'file_name', 'resource');
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $fileName);
    }

    public function downloadReport(Report $report): StreamedResponse
    {
        abort_if($report->trashed() || ! $report->file_path || ! Storage::disk('local')->exists($report->file_path), 404);

        return Storage::disk('local')->download($report->file_path, $report->file_name ?? 'report');
    }

    public function reviewReport(Request $request, Report $report, GeminiReportAnalyzer $analyzer): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'visibility' => ['sometimes', 'required', 'in:private,member,public'],
            'reporting_period' => ['sometimes', 'nullable', 'string', 'max:100'],
            'review_feedback' => ['nullable', 'string', 'max:10000'],
        ]);
    
        abort_unless(in_array($report->status, ['pending_review', 'rejected'], true), 422);
    
        $approved = $data['decision'] === 'approve';
    
        // 1. Slow external call happens BEFORE and OUTSIDE the transaction.
        $analysis = null;
        $analysisError = null;
    
        if ($approved) {
            set_time_limit(180);
            $existing = $report->aiAnalysis; // hasOne(ReportAiAnalysis::class)
            try {
                // returns null when files are unchanged since the last successful analysis
                $analysis = $analyzer->analyze(
                    $report,
                    $existing?->status === 'ready' ? $existing->files_hash : null
                );
            } catch (\Throwable $e) {
                report($e);
                $analysisError = \Illuminate\Support\Str::limit($e->getMessage(), 500);
            }
        }
        // 2. Fast DB work only.
        DB::transaction(function () use ($request, $report, $data, $approved, $analysis, $analysisError): void {
            $report = Report::lockForUpdate()->findOrFail($report->id);
            abort_unless(in_array($report->status, ['pending_review', 'rejected'], true), 422);
    
            $old = $this->contentAudit($report);
            $report->status = $approved ? 'published' : 'rejected';
            $report->visibility = $approved ? ($data['visibility'] ?? 'member') : 'private';
            $report->reporting_period = $data['reporting_period'] ?? $report->reporting_period;
            $report->review_feedback = $data['review_feedback'] ?? null;
            $report->reviewed_by = $request->user()->id;
            $report->reviewed_at = now();
            $report->published_at = $approved ? now() : null;
            $report->save();
    
            if ($analysis) {
                $report->aiAnalysis()->updateOrCreate([], [
                    'status'       => 'ready',
                    'summary_json' => $analysis['json'],
                    'summary_html' => $analysis['html'],
                    'model'        => $analysis['model'],
                    'files_hash'   => $analysis['files_hash'],
                    'error'        => null,
                    'generated_at' => now(),
                ]);
            } elseif ($analysisError && $report->aiAnalysis?->status !== 'ready') {
                // approval still succeeds; keep any earlier good analysis untouched
                $report->aiAnalysis()->updateOrCreate([], ['status' => 'failed', 'error' => $analysisError]);
            }
    
            AuditLog::record(
                $request->user(),
                $approved ? 'report.approved' : 'report.rejected',
                $report, $old, $this->contentAudit($report)
            );
        });
    
        return response()->json([
            'id' => $report->id,
            'status' => $report->fresh()->status,
            'ai_status' => $approved ? ($analysisError ? 'failed' : 'ready') : null,
        ]);
    }

    public function regenerateReportAiSummary(Report $report, GeminiReportAnalyzer $analyzer): JsonResponse
    {
        abort_unless($report->status === 'published', 422, 'Only published reports can have an AI summary.');

        $analysisRecord = DB::transaction(function () use ($report) {
            $lockedReport = Report::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless($lockedReport->status === 'published', 422, 'Only published reports can have an AI summary.');

            $existing = $lockedReport->aiAnalysis()->lockForUpdate()->first();
            if ($existing?->status === 'ready' && filled(data_get($existing->summary_json, 'summary'))) {
                abort(409, 'This report already has a ready AI summary.');
            }
            if ($existing?->status === 'processing' && $existing->updated_at?->gt(now()->subMinutes(5))) {
                abort(409, 'AI summary generation is already in progress.');
            }

            return $lockedReport->aiAnalysis()->updateOrCreate([], [
                'status' => 'processing',
                'summary_json' => null,
                'summary_html' => null,
                'model' => null,
                'files_hash' => null,
                'error' => null,
                'generated_at' => null,
            ]);
        });

        set_time_limit(180);

        try {
            $analysis = $analyzer->analyze($report);
            if ($analysis === null) {
                throw new \RuntimeException('The AI analyzer did not return a new summary.');
            }
        } catch (\Throwable $exception) {
            report($exception);
            $analysisRecord->update([
                'status' => 'failed',
                'summary_json' => null,
                'summary_html' => null,
                'model' => null,
                'files_hash' => null,
                'error' => Str::limit($exception->getMessage(), 500),
                'generated_at' => null,
            ]);

            return response()->json([
                'message' => 'AI summary generation failed. Please review the AI configuration or try again.',
                'ai_status' => 'failed',
            ], 422);
        }

        $analysisRecord->update([
            'status' => 'ready',
            'summary_json' => $analysis['json'],
            'summary_html' => $analysis['html'],
            'model' => $analysis['model'],
            'files_hash' => $analysis['files_hash'],
            'error' => null,
            'generated_at' => now(),
        ]);

        return response()->json([
            'id' => $report->id,
            'ai_status' => 'ready',
        ]);
    }

    // public function reviewReport(Request $request, Report $report): JsonResponse
    // {
    //     $data = $request->validate([
    //         'decision' => ['required', 'in:approve,reject'],
    //         'visibility' => ['sometimes', 'required', 'in:private,member,public'],
    //         'reporting_period' => ['sometimes', 'nullable', 'string', 'max:100'],
    //         'review_feedback' => ['nullable', 'string', 'max:10000'],
    //     ]);

    //     abort_unless(in_array($report->status, ['pending_review', 'rejected'], true), 422);

    //     DB::transaction(function () use ($request, $report, $data): void {
    //         $old = $this->contentAudit($report);
    //         $approved = $data['decision'] === 'approve';
    //         $report->status = $approved ? 'published' : 'rejected';
    //         $report->visibility = $approved ? ($data['visibility'] ?? 'member') : 'private';
    //         $report->reporting_period = $data['reporting_period'] ?? $report->reporting_period;
    //         $report->review_feedback = $data['review_feedback'] ?? null;
    //         $report->reviewed_by = $request->user()->id;
    //         $report->reviewed_at = now();
    //         $report->published_at = $approved ? now() : null;
    //         $report->save();
    //         AuditLog::record($request->user(), $approved ? 'report.approved' : 'report.rejected', $report, $old, $this->contentAudit($report));
    //     });

    //     return response()->json(['id' => $report->id, 'status' => $report->status]);
    // }

    private function resourceData(Request $request, bool $creating): array
    {
        if (is_string($request->input('tags'))) {
            $request->merge(['tags' => array_values(array_filter(array_map('trim', explode(',', $request->input('tags')))))]);
        }

        $data = $request->validate([
            'business_id' => ['nullable', 'integer', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'resource_type' => ['required', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:100'],
            'resource_group' => ['nullable', Rule::in(['general', 'guideline', 'how_to'])],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:60'],
            'content' => ['nullable', 'string', 'max:50000'],
            'file_url' => ['nullable', 'url', 'max:2048'],
            'video_embed_url' => ['nullable', 'url', 'max:2048'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'requires_acknowledgment' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'visibility' => ['required', Rule::in(['private', 'member', 'public'])],
            'is_featured' => ['sometimes', 'boolean'],
            'file' => [$creating ? 'nullable' : 'sometimes', 'file', 'mimes:'.self::FILE_TYPES.',mp4,webm', 'max:20480'],
        ]);

        if (($data['business_id'] ?? null) && ! Business::query()->whereKey($data['business_id'])->whereNull('deleted_at')->exists()) {
            abort(422, 'The selected business is unavailable.');
        }
        if (! empty($data['video_embed_url'])) {
            $host = strtolower((string) parse_url($data['video_embed_url'], PHP_URL_HOST));
            if (! in_array($host, ['youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'player.vimeo.com'], true)) {
                abort(422, 'Video embeds must use YouTube or Vimeo.');
            }
        }

        return $data;
    }

    private function reportData(Request $request, bool $creating): array
    {
        return $request->validate([
            'business_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'reporting_period' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'visibility' => ['required', Rule::in(['private', 'member', 'public'])],
            'file' => [$creating ? 'required' : 'sometimes', 'file', 'mimes:'.self::FILE_TYPES, 'max:20480'],
        ]);
    }

    private function resourceAttributes(Request $request, array $data): array
    {
        $attributes = collect($data)->except(['file'])->all();
        $attributes['category'] = $attributes['category'] ?? 'General';
        $attributes['resource_group'] = $attributes['resource_group'] ?? 'general';
        $attributes['version'] = $attributes['version'] ?? 1;
        $attributes['requires_acknowledgment'] = $attributes['requires_acknowledgment'] ?? false;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $attributes += [
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $file->storeAs('admin/resources', Str::uuid().'.'.$file->extension(), 'local'),
                'file_url' => null,
            ];
        }
        $attributes['published_at'] = ($data['status'] ?? null) === 'published'
            ? ($data['published_at'] ?? now())
            : null;

        return $attributes;
    }

    private function resourceSnapshot(Resource $resource): array
    {
        return $resource->only([
            'title', 'description', 'content', 'resource_type', 'category', 'resource_group', 'tags',
            'file_name', 'file_path', 'file_url', 'video_embed_url', 'estimated_minutes',
            'status', 'visibility', 'requires_acknowledgment',
        ]);
    }

    private function notifyGuidelineBusinesses(Resource $resource): void
    {
        $businesses = $resource->business_id
            ? Business::query()->whereKey($resource->business_id)->whereNotNull('contact_email')->get()
            : Business::query()->whereNull('deleted_at')->whereNotNull('contact_email')->get();

        foreach ($businesses as $business) {
            Mail::raw(
                "A new program guideline requires your acknowledgment: {$resource->title}. Sign in to your member dashboard to review it.",
                fn ($message) => $message->to($business->contact_email)->subject('A new guideline requires your acknowledgment'),
            );
        }
    }

    private function reportAttributes(Request $request, array $data): array
    {
        $attributes = collect($data)->except(['file'])->all();
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $attributes += [
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $file->storeAs('admin/reports', Str::uuid().'.'.$file->extension(), 'local'),
                'file_disk' => 'local',
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ];
        }
        $attributes['published_at'] = ($data['status'] ?? null) === 'published'
            ? ($data['published_at'] ?? now())
            : null;

        return $attributes;
    }

    private function contentAudit(Resource|Report $content): array
    {
        return [
            'business_id' => $content->business_id,
            'title' => $content->title,
            'status' => $content->status,
            'visibility' => $content->visibility,
            'file_name' => $content->file_name,
        ];
    }
}
