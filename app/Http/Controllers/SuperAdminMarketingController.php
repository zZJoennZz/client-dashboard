<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\MarketingActivity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        DB::transaction(function () use ($request, $data, $activity): void {
            $old = $this->auditData($activity);
            $activity->fill($data)->save();
            AuditLog::record($request->user(), 'marketing.updated', $activity, $old, $this->auditData($activity));
        });

        return response()->json(['id' => $activity->id, 'status' => $activity->status]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'promotion_type' => ['required', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'max:100'],
            'scheduled_at' => ['nullable', 'date'],
            'responsible_admin_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('account_type', User::ACCOUNT_TYPE_SUPER_ADMIN)],
            'caption' => ['nullable', 'string', 'max:10000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'member_description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'published_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['required', Rule::in(['planned', 'scheduled', 'completed', 'cancelled'])],
            'completed_at' => ['nullable', 'date'],
        ]);

        return $data;
    }

    private function auditData(MarketingActivity $activity): array
    {
        return [
            'business_id' => $activity->business_id,
            'title' => $activity->title,
            'promotion_type' => $activity->promotion_type,
            'platform' => $activity->platform,
            'scheduled_at' => $activity->scheduled_at?->toDateTimeString(),
            'status' => $activity->status,
            'responsible_admin_id' => $activity->responsible_admin_id,
        ];
    }
}
