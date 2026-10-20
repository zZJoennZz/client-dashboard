<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\MarketingActivity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SuperAdminMarketingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $activity = DB::transaction(function () use ($request, $data): MarketingActivity {
            $data['scope'] = $data['scope'] ?? (($data['business_id'] ?? null) === null ? 'program' : 'business');
            $activity = MarketingActivity::query()->create($data + ['created_by' => $request->user()->id]);
            AuditLog::record($request->user(), 'marketing.created', $activity, [], $this->auditData($activity));

            return $activity;
        });

        if ($activity->scope === 'business' && $activity->scheduled_at && $activity->status === 'scheduled') {
            $this->notifyBusiness($activity);
        }

        return response()->json(['id' => $activity->id], 201);
    }

    public function update(Request $request, MarketingActivity $activity): JsonResponse
    {
        $data = $this->validated($request);
        $oldStatus = $activity->status;
        DB::transaction(function () use ($request, $data, $activity): void {
            $data['scope'] = $data['scope'] ?? (($data['business_id'] ?? null) === null ? 'program' : 'business');
            $old = $this->auditData($activity);
            $activity->fill($data)->save();
            AuditLog::record($request->user(), 'marketing.updated', $activity, $old, $this->auditData($activity));
        });

        if ($oldStatus === 'planned' && $activity->status === 'scheduled' && $activity->business_id) {
            $this->notifyBusiness($activity);
        }

        return response()->json(['id' => $activity->id, 'status' => $activity->status]);
    }

    public function duplicate(Request $request, MarketingActivity $activity): JsonResponse
    {
        $copy = DB::transaction(function () use ($request, $activity): MarketingActivity {
            $copy = $activity->replicate();
            $copy->title = $activity->title.' (copy)';
            $copy->status = 'planned';
            $copy->completed_at = null;
            $copy->published_url = null;
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
            'business_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'scope' => ['sometimes', Rule::in(['program', 'business'])],
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

        $data['scope'] ??= ($data['business_id'] ?? null) === null ? 'program' : 'business';
        if ($data['scope'] === 'business' && empty($data['business_id'])) {
            abort(422, 'A business is required for a business highlight.');
        }
        if ($data['scope'] === 'program') {
            $data['business_id'] = null;
        }

        return $data;
    }

    private function auditData(MarketingActivity $activity): array
    {
        return [
            'business_id' => $activity->business_id,
            'scope' => $activity->scope,
            'title' => $activity->title,
            'promotion_type' => $activity->promotion_type,
            'platform' => $activity->platform,
            'scheduled_at' => $activity->scheduled_at?->toDateTimeString(),
            'status' => $activity->status,
            'responsible_admin_id' => $activity->responsible_admin_id,
        ];
    }

    private function notifyBusiness(MarketingActivity $activity): void
    {
        $email = $activity->business?->contact_email;
        if ($email) {
            \Illuminate\Support\Facades\Mail::raw(
                "A social media highlight has been scheduled for your business: {$activity->title}.\nScheduled: ".($activity->scheduled_at?->toDayDateTimeString() ?? 'To be confirmed.'),
                fn ($message) => $message->to($email)->subject('Your social media highlight is scheduled'),
            );
        }
    }
}
