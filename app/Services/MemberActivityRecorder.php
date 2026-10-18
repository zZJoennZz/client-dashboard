<?php

namespace App\Services;

use App\Models\MemberActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MemberActivityRecorder
{
    public function record(
        User $user,
        string $type,
        ?int $businessId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectLabel = null,
        ?int $eventId = null,
        bool $deduplicateDaily = false,
    ): MemberActivity {
        return DB::transaction(function () use (
            $user,
            $type,
            $businessId,
            $subjectType,
            $subjectId,
            $subjectLabel,
            $eventId,
            $deduplicateDaily,
        ): MemberActivity {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $today = now()->toDateString();

            if ($deduplicateDaily && $subjectType !== null && $subjectId !== null) {
                $existing = MemberActivity::query()
                    ->where('user_id', $user->id)
                    ->where('activity_type', $type)
                    ->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId)
                    ->whereDate('created_at', $today)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            return MemberActivity::query()->create([
                'user_id' => $user->id,
                'business_id' => $businessId,
                'event_id' => $eventId,
                'activity_type' => $type,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_label' => $subjectLabel,
                'activity_date' => $today,
                'metadata' => null,
            ]);
        });
    }
}
