<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businessIds = $this->managedBusinessIds($request->user());
        $members = User::query()
            ->with(['profile', 'businessAssociations.business'])
            ->where('account_type', User::ACCOUNT_TYPE_REGULAR)
            ->whereHas('businessAssociations', fn (Builder $associations) => $associations
                ->whereIn('business_id', $businessIds)
                ->where('status', 'active')
                ->where(fn (Builder $roles) => $roles
                    ->where('role', 'member')
                    ->where('access_level', 'member')))
            ->whereDoesntHave('businessAssociations', fn (Builder $associations) => $associations
                ->where('status', 'active')
                ->where(fn (Builder $roles) => $roles
                    ->whereIn('role', ['owner', 'admin'])
                    ->orWhereIn('access_level', ['owner', 'admin'])))
            ->orderBy('email')
            ->get();

        return response()->json([
            'data' => $members->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->profile?->full_name ?? 'Profile incomplete',
                'email' => $member->email,
                'first_name' => $member->profile?->first_name,
                'last_name' => $member->profile?->last_name,
                'phone' => $member->profile?->phone,
                'job_title' => $member->profile?->job_title,
                'businesses' => $member->businessAssociations
                    ->where('status', 'active')
                    ->whereIn('business_id', $businessIds)
                    ->filter(fn (BusinessUser $association): bool => $association->role === 'member'
                        && $association->access_level === 'member')
                    ->map(fn (BusinessUser $association): string => $association->business?->company_name ?? '')
                    ->filter()
                    ->unique()
                    ->values(),
            ])->values(),
            'can_write' => $request->user()->canAccessDashboardModule('users', 'write'),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $businessIds = $this->managedBusinessIds($request->user());
        abort_unless(
            $user->account_type === User::ACCOUNT_TYPE_REGULAR
            && ! $user->isBusinessAdministrator()
            && $user->businessAssociations()
                ->whereIn('business_id', $businessIds)
                ->where('status', 'active')
                ->where('role', 'member')
                ->where('access_level', 'member')
                ->exists(),
            404
        );

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
        ]);

        $profile = $user->profile()->firstOrNew();
        $oldValues = $profile->only(['first_name', 'last_name', 'phone', 'job_title']);
        DB::transaction(function () use ($profile, $data, $request, $user, $oldValues): void {
            $profile->fill($data);
            $user->profile()->save($profile);
            AuditLog::record(
                $request->user(),
                'member.profile_updated_by_administrator',
                $user,
                $oldValues,
                $profile->only(['first_name', 'last_name', 'phone', 'job_title']),
            );
        });

        return response()->json([
            'message' => 'Member profile updated.',
            'member' => [
                'id' => $user->id,
                'name' => $profile->full_name,
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'phone' => $profile->phone,
                'job_title' => $profile->job_title,
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function managedBusinessIds(User $administrator): array
    {
        return $administrator->businessAssociations()
            ->where('status', 'active')
            ->where(fn (Builder $roles) => $roles
                ->whereIn('role', ['owner', 'admin'])
                ->orWhereIn('access_level', ['owner', 'admin']))
            ->pluck('business_id')
            ->all();
    }
}
