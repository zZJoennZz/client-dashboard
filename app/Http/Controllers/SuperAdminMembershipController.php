<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SuperAdminMembershipController extends Controller
{
    public function update(Request $request, Membership $membership): JsonResponse
    {
        $knownStatuses = Membership::query()->distinct()->pluck('status')->all();
        $data = $request->validate([
            'status' => ['sometimes', 'required', 'string', Rule::in($knownStatuses)],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'cancellation_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('status', $data) && $data['status'] === 'cancelled' && empty($data['cancellation_reason'])) {
            throw ValidationException::withMessages(['cancellation_reason' => 'A reason is required when cancelling a membership.']);
        }

        DB::transaction(function () use ($membership, $data, $request): void {
            $locked = Membership::query()->lockForUpdate()->findOrFail($membership->id);
            $old = $this->auditValues($locked);

            if (array_key_exists('status', $data)) {
                $locked->status = $data['status'];
                if ($data['status'] === 'cancelled') {
                    $locked->cancelled_at = now();
                    $locked->cancellation_reason = $data['cancellation_reason'];
                }
            }

            if (array_key_exists('end_date', $data)) {
                $locked->end_date = $data['end_date'];
            }

            $locked->save();
            AuditLog::record($request->user(), 'membership.adjusted', $locked, $old, $this->auditValues($locked));
        });

        return response()->json($this->auditValues($membership->fresh()));
    }

    public function extend(Request $request, Membership $membership): JsonResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        DB::transaction(function () use ($membership, $data, $request): void {
            $locked = Membership::query()->lockForUpdate()->findOrFail($membership->id);
            $old = $this->auditValues($locked);
            $anchor = $locked->end_date && $locked->end_date->isFuture()
                ? $locked->end_date
                : now();
            $locked->end_date = $anchor->copy()->addDays($data['days']);
            $locked->save();

            AuditLog::record(
                $request->user(),
                'membership.complimentary_extension',
                $locked,
                $old,
                $this->auditValues($locked),
                ['source' => 'super-admin'],
            );
        });

        return response()->json($this->auditValues($membership->fresh()));
    }

    private function auditValues(Membership $membership): array
    {
        return [
            'id' => $membership->id,
            'status' => $membership->status,
            'payment_status' => $membership->payment_status,
            'start_date' => $membership->start_date?->toDateString(),
            'end_date' => $membership->end_date?->toDateString(),
        ];
    }
}
