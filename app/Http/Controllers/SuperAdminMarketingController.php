<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MarketingActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SuperAdminMarketingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $activity = DB::transaction(function () use ($request, $data): MarketingActivity {
            $activity = MarketingActivity::query()->create($data + ['created_by' => $request->user()->id]);
            AuditLog::record($request->user(), 'marketing.created', $activity, [], $this->auditData($activity));

            return $activity;
        });

        return response()->json(['id' => $activity->id], 201);
    }

    public function update(Request $request, MarketingActivity $activity): JsonResponse
    {
        $data = $this->validated($request);
        $oldStatus = $activity->status;
        DB::transaction(function () use ($request, $data, $activity): void {
            $old = $this->auditData($activity);
            $activity->fill($data)->save();
            AuditLog::record($request->user(), 'marketing.updated', $activity, $old, $this->auditData($activity));
        });

        return response()->json(['id' => $activity->id, 'status' => $activity->status]);
    }

    public function duplicate(Request $request, MarketingActivity $activity): JsonResponse
    {
        $copy = DB::transaction(function () use ($request, $activity): MarketingActivity {
            $copy = $activity->replicate();
            $copy->title = $activity->title.' (copy)';
            $copy->status = 'planned';
            $copy->completed_at = null;
            $copy->scheduled_at = $activity->scheduled_at?->copy()->addMonth();
            $copy->created_by = $request->user()->id;
            $copy->save();
            AuditLog::record($request->user(), 'marketing.duplicated', $copy, [], $this->auditData($copy));

            return $copy;
        });

        return response()->json(['id' => $copy->id], 201);
    }

    public function archive(Request $request, MarketingActivity $activity): Response
    {
        DB::transaction(function () use ($request, $activity): void {
            $old = $this->auditData($activity);
            $activity->delete();
            AuditLog::record($request->user(), 'marketing.archived', $activity, $old, ['status' => 'archived']);
        });

        return response()->noContent();
    }

    public function setStatus(Request $request, MarketingActivity $activity): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['planned', 'scheduled', 'completed', 'cancelled'])]]);
        $old = $this->auditData($activity);
        $activity->status = $data['status'];
        if ($activity->status === 'completed' && $activity->completed_at === null) {
            $activity->completed_at = now();
        }
        $activity->save();
        AuditLog::record($request->user(), 'marketing.status_updated', $activity, $old, $this->auditData($activity));

        return response()->json(['id' => $activity->id, 'status' => $activity->status]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'promotion_type' => ['required', Rule::in(config('marketing.promotion_types'))],
            'platforms' => ['nullable', 'array'],
            'platforms.*.platform' => ['required', 'string', Rule::in(config('marketing.platforms')), 'distinct'],
            'platforms.*.url' => ['nullable', 'url', 'max:2048'],
            'scheduled_at' => ['nullable', 'date'],
            'caption' => ['nullable', 'string', 'max:10000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'member_description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in(['planned', 'scheduled', 'completed', 'cancelled'])],
            'completed_at' => ['nullable', 'date'],
        ]);

        $data['platforms'] = array_values($data['platforms'] ?? []);

        return $data;
    }

    private function auditData(MarketingActivity $activity): array
    {
        return [
            'title' => $activity->title,
            'promotion_type' => $activity->promotion_type,
            'platforms' => $activity->platforms ?? [],
            'scheduled_at' => $activity->scheduled_at?->toDateTimeString(),
            'status' => $activity->status,
        ];
    }
}
