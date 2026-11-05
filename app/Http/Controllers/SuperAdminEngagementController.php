<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuperAdminEngagementController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'business_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'activity_type' => ['nullable', Rule::in([
                'login', 'report_viewed', 'report_download', 'report_downloaded',
                'resource_viewed', 'resource_download', 'resource_downloaded',
                'event_rsvp', 'event_registered', 'event_cancelled', 'event_attended',
            ])],
            'registration_status' => ['nullable', Rule::in(['registered', 'confirmed', 'cancelled', 'attended'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'activity_page' => ['nullable', 'integer', 'min:1'],
            'summary_page' => ['nullable', 'integer', 'min:1'],
            'event_page' => ['nullable', 'integer', 'min:1'],
            'participation_page' => ['nullable', 'integer', 'min:1'],
            'summary_search' => ['nullable', 'string', 'max:150'],
            'summary_sort' => ['nullable', Rule::in(['name', 'last_login', 'engagement'])],
            'summary_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $activityQuery = MemberActivity::query()
            ->with(['user.profile', 'business'])
            ->when(
                isset($filters['business_id']),
                fn (Builder $query) => $query->where('business_id', $filters['business_id'])
            )
            ->when(isset($filters['activity_type']), function (Builder $query) use ($filters): void {
                $activityTypes = match ($filters['activity_type']) {
                    'report_downloaded' => ['report_download', 'report_downloaded'],
                    'resource_downloaded' => ['resource_download', 'resource_downloaded'],
                    default => [$filters['activity_type']],
                };

                $query->whereIn('activity_type', $activityTypes);
            })
            ->when(
                isset($filters['from']),
                fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from'])
            )
            ->when(
                isset($filters['to']),
                fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to'])
            )
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('subject_label', 'like', $search)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('email', 'like', $search)
                            ->orWhereHas('profile', fn (Builder $profile) => $profile
                                ->where('first_name', 'like', $search)
                                ->orWhere('last_name', 'like', $search)))
                        ->orWhereHas('business', fn (Builder $business) => $business
                            ->where('company_name', 'like', $search));
                });
            });

        $activities = $activityQuery
            ->latest()
            ->paginate(20, ['*'], 'activity_page')
            ->through(fn (MemberActivity $activity): array => [
                'id' => $activity->id,
                'type' => $activity->activity_type,
                'subject' => $activity->subject_label,
                'member' => $activity->user?->profile?->full_name
                    ?? $activity->user?->email
                    ?? 'Unknown member',
                'business' => $activity->business?->company_name,
                'occurred_at' => $activity->created_at,
            ]);

        $summaryActivity = MemberActivity::query()
            ->where('created_at', '>=', now()->subDays(30)->startOfDay());

        $businesses = Business::query()
            ->whereNull('clients.deleted_at')
            ->when(
                isset($filters['business_id']),
                fn (Builder $query) => $query->whereKey($filters['business_id'])
            )
            ->when(
                isset($filters['summary_search']),
                fn (Builder $query) => $query->where(
                    'clients.company_name',
                    'like',
                    '%'.$filters['summary_search'].'%'
                )
            )
            ->select(['clients.id', 'clients.company_name'])
            ->addSelect([
                'last_login_at' => MemberActivity::query()
                    ->selectRaw('MAX(member_activities.created_at)')
                    ->whereColumn('member_activities.business_id', 'clients.id')
                    ->where('member_activities.activity_type', 'login'),

                'logins_30_days' => (clone $summaryActivity)
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('member_activities.business_id', 'clients.id')
                    ->where('member_activities.activity_type', 'login'),

                'reports_viewed_30_days' => (clone $summaryActivity)
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('member_activities.business_id', 'clients.id')
                    ->where('member_activities.activity_type', 'report_viewed'),

                'events_registered_30_days' => EventRegistration::query()
                    ->selectRaw('COUNT(*)')
                    ->where('event_registrations.registered_at', '>=', now()->subDays(30)->startOfDay())
                    ->whereHas('event', fn (Builder $event) => $event
                        ->whereColumn('events.business_id', 'clients.id')),

                'attendance_recorded_30_days' => EventRegistration::query()
                    ->selectRaw('COUNT(*)')
                    ->whereNotNull('event_registrations.attended_at')
                    ->where('event_registrations.status', 'attended')
                    ->where('event_registrations.attended_at', '>=', now()->subDays(30)->startOfDay())
                    ->whereHas('event', fn (Builder $event) => $event
                        ->whereColumn('events.business_id', 'clients.id')),
            ]);

        $summarySort = $filters['summary_sort'] ?? 'engagement';
        $summaryDirection = $filters['summary_direction'] ?? 'desc';

        if ($summarySort === 'name') {
            $businesses->orderBy('clients.company_name', $summaryDirection);
        } elseif ($summarySort === 'last_login') {
            $businesses->orderByRaw('last_login_at IS NULL')
                ->orderBy('last_login_at', $summaryDirection);
        } else {
            $businesses->orderByRaw(
                '(logins_30_days + reports_viewed_30_days + events_registered_30_days) '.$summaryDirection
            )->orderBy('clients.company_name');
        }

        $businessSummaries = $businesses
            ->paginate(15, ['*'], 'summary_page')
            ->through(fn (Business $business): array => [
                'id' => $business->id,
                'business' => $business->company_name,
                'last_login_at' => $business->last_login_at,
                'logins_30_days' => (int) $business->logins_30_days,
                'reports_viewed_30_days' => (int) $business->reports_viewed_30_days,
                'events_registered_30_days' => (int) $business->events_registered_30_days,
                'attendance_recorded_30_days' => (int) $business->attendance_recorded_30_days,
            ]);

        $eventTotalsQuery = Event::query()
            ->with(['business:id,company_name'])
            ->withCount([
                'registrations' => function (Builder $query) use ($filters): void {
                    $query->when(
                        isset($filters['registration_status']),
                        fn (Builder $query) => $query->where(
                            'status',
                            $filters['registration_status']
                        )
                    );
                    if (($filters['registration_status'] ?? null) === 'attended') {
                        $query->whereNotNull('attended_at');
                    }
                },
            ])
            ->withCount([
                'registrations as attendance_recorded' => function (Builder $query) use ($filters): void {
                    $query->whereNotNull('attended_at')
                        ->where('status', 'attended')
                        ->when(
                            isset($filters['registration_status']),
                            fn (Builder $query) => $query->where(
                                'status',
                                $filters['registration_status']
                            )
                        );
                },
            ])
            ->when(
                isset($filters['business_id']),
                fn (Builder $query) => $query->where('business_id', $filters['business_id'])
            )
            ->when(
                isset($filters['event_id']),
                fn (Builder $query) => $query->whereKey($filters['event_id'])
            )
            ->when(
                isset($filters['from']),
                fn (Builder $query) => $query->whereDate('starts_at', '>=', $filters['from'])
            )
            ->when(
                isset($filters['to']),
                fn (Builder $query) => $query->whereDate('starts_at', '<=', $filters['to'])
            )
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';

                $query->where(fn (Builder $query) => $query
                    ->where('title', 'like', $search)
                    ->orWhereHas('business', fn (Builder $business) => $business
                        ->where('company_name', 'like', $search)));
            });

        $eventTotals = $eventTotalsQuery
            ->orderByDesc('starts_at')
            ->paginate(15, ['*'], 'event_page')
            ->through(fn (Event $event): array => [
                'id' => $event->id,
                'event' => $event->title,
                'business' => $event->business?->company_name,
                'starts_at' => $event->starts_at,
                'registrations' => $event->registrations_count,
                'attendance_recorded' => $event->attendance_recorded,
            ]);

        $participationQuery = EventRegistration::query()
            ->with(['user.profile', 'user:id,email', 'event.business'])
            ->when(
                isset($filters['business_id']),
                fn (Builder $query) => $query->whereHas(
                    'event',
                    fn (Builder $event) => $event->where('business_id', $filters['business_id'])
                )
            )
            ->when(
                isset($filters['event_id']),
                fn (Builder $query) => $query->where('event_id', $filters['event_id'])
            )
            ->when(
                isset($filters['registration_status']),
                fn (Builder $query) => $query->where('status', $filters['registration_status'])
            )
            ->when(
                ($filters['registration_status'] ?? null) === 'attended',
                fn (Builder $query) => $query->whereNotNull('attended_at')
            )
            ->when(
                isset($filters['from']),
                fn (Builder $query) => $query->whereDate('registered_at', '>=', $filters['from'])
            )
            ->when(
                isset($filters['to']),
                fn (Builder $query) => $query->whereDate('registered_at', '<=', $filters['to'])
            )
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';

                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('user', fn (Builder $user) => $user
                        ->where('email', 'like', $search)
                        ->orWhereHas('profile', fn (Builder $profile) => $profile
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)))
                        ->orWhereHas('event', fn (Builder $event) => $event
                            ->where('title', 'like', $search)
                            ->orWhereHas('business', fn (Builder $business) => $business
                                ->where('company_name', 'like', $search)));
                });
            });

        $participation = $participationQuery
            ->latest('registered_at')
            ->paginate(20, ['*'], 'participation_page')
            ->through(fn (EventRegistration $registration): array => [
                'id' => $registration->id,
                'member' => $registration->user?->profile?->full_name
                    ?? $registration->user?->email
                    ?? 'Unknown member',
                'business' => $registration->event?->business?->company_name,
                'event_id' => $registration->event_id,
                'event' => $registration->event?->title,
                'status' => $registration->status,
                'registered_at' => $registration->registered_at,
                'attended_at' => $registration->attended_at,
            ]);

        $recentActivity = MemberActivity::query()
            ->where('created_at', '>=', now()->subDays(30)->startOfDay());

        return response()->json([
            'metrics' => [
                'rsvps_30_days' => (clone $recentActivity)
                    ->whereIn('activity_type', ['event_rsvp', 'event_registered'])
                    ->count(),

                'active_registrations' => EventRegistration::query()
                    ->whereIn('status', ['registered', 'confirmed'])
                    ->count(),

                'recorded_attendance' => EventRegistration::query()
                    ->whereNotNull('attended_at')
                    ->where('status', 'attended')
                    ->count(),

                'members_active_30_days' => (clone $recentActivity)
                    ->distinct('user_id')
                    ->count('user_id'),
            ],

            'attendance_tracked' => true,

            'businesses' => Business::query()
                ->whereNull('deleted_at')
                ->orderBy('company_name')
                ->get(['id', 'company_name']),

            'events' => Event::query()
                ->with('business:id,company_name')
                ->orderBy('title')
                ->get(['id', 'business_id', 'title'])
                ->map(fn (Event $event): array => [
                    'id' => $event->id,
                    'business_id' => $event->business_id,
                    'title' => $event->title,
                    'business' => $event->business?->company_name,
                ]),

            'business_summaries' => $this->paginated($businessSummaries),
            'event_totals' => $this->paginated($eventTotals),
            'activities' => $this->paginated($activities),
            'participation' => $this->paginated($participation),
        ]);
    }

    private function paginated(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}