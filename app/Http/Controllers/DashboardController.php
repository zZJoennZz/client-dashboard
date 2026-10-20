<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MarketingActivity;
use App\Models\MemberActivity;
use App\Models\Membership;
use App\Models\Report;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('dashboard.index');
    }

    public function data(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $user->loadMissing('profile');

        $businesses = $isSuperAdmin
            ? Business::query()
                ->with(['memberships' => fn ($query) => $query->with('plan')->latest(), 'categories', 'images'])
                ->orderBy('company_name')
                ->get()
            : $user->businesses()
                ->wherePivot('status', 'active')
                ->with(['memberships' => fn ($query) => $query->with('plan')->latest(), 'categories', 'images'])
                ->orderBy('company_name')
                ->get();

        $business = $isSuperAdmin
            ? null
            : ($businesses->first(fn (Business $business) => $business->pivot->is_primary)
                ?? $businesses->first());
        $businessIds = $businesses->modelKeys();
        $businessNames = $businesses->pluck('company_name', 'id');

        $reportsQuery = Report::query()
            ->whereIn('business_id', $businessIds)
            ->latest('published_at');
        if (! $isSuperAdmin) {
            $reportsQuery->where('status', 'published')
                ->whereIn('visibility', ['member', 'public']);
        }
        $reports = $reportsQuery
            ->get(['id', 'business_id', 'title', 'description', 'reporting_period', 'file_name', 'file_size', 'published_at', 'status', 'visibility', 'file_path'])
            ->map(function (Report $report) use ($businessNames, $isSuperAdmin): array {
                $record = $report->toArray();
                unset($record['file_path']);
                $record['download_url'] = $report->file_path
                    ? route($isSuperAdmin ? 'super-admin.reports.download' : 'dashboard.files.reports', $report)
                    : null;

                return $this->withBusinessName($record, $businessNames);
            });

        $eventsQuery = Event::query()
            ->whereIn('business_id', $businessIds)
            ->orderBy('starts_at');
        if (! $isSuperAdmin) {
            $eventsQuery->where('is_published', true)
                ->whereIn('status', ['published', 'scheduled'])
                ->where('ends_at', '>=', now());
        }
        $events = $eventsQuery
            ->withCount(['registrations as active_registrations' => fn (Builder $query) => $query->whereIn('status', ['registered', 'confirmed'])])
            ->get(['id', 'business_id', 'title', 'description', 'location', 'starts_at', 'ends_at', 'status', 'is_published', 'capacity', 'registration_enabled', 'registration_deadline']);
        $registrations = $events->isEmpty()
            ? collect()
            : EventRegistration::query()
                ->where('user_id', $user->id)
                ->whereIn('event_id', $events->modelKeys())
                ->get()
                ->keyBy('event_id');
        $events = $events->map(function (Event $event) use ($businessNames, $registrations, $isSuperAdmin): array {
            $registration = $registrations->get($event->id);
            $record = $this->withBusinessName($event->toArray(), $businessNames);
            $record['registration'] = $registration?->only(['id', 'status']);
            $record['registration_url'] = route('dashboard.events.register', $event);
            $record['cancellation_url'] = $registration ? route('dashboard.events.cancel', $registration) : null;
            $record['can_register'] = ! $isSuperAdmin
                && $event->registration_enabled
                && (! $event->registration_deadline || $event->registration_deadline->isFuture())
                && ($event->capacity === null || $event->active_registrations < $event->capacity)
                && ! in_array($registration?->status, ['registered', 'confirmed'], true);

            return $record;
        });

        $resources = Resource::query()
            ->where(function (Builder $query) use ($businessIds): void {
                $query->whereNull('business_id')
                    ->orWhereIn('business_id', $businessIds);
            })
            ->when(! $isSuperAdmin, function (Builder $query): void {
                $query->where('status', 'published')
                    ->whereIn('visibility', ['member', 'public']);
            })
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->get(['id', 'business_id', 'title', 'description', 'content', 'resource_type', 'category', 'resource_group', 'tags', 'estimated_minutes', 'video_embed_url', 'file_name', 'is_featured', 'requires_acknowledgment', 'version', 'published_at', 'status', 'visibility', 'file_path', 'file_url'])
            ->map(function (Resource $resource) use ($businessNames, $isSuperAdmin, $businessIds): array {
                $record = $resource->toArray();
                unset($record['file_path']);
                $record['download_url'] = $resource->file_path
                    ? route($isSuperAdmin ? 'super-admin.resources.download' : 'dashboard.files.resources', $resource)
                    : null;
                $acknowledgment = $resource->requires_acknowledgment && ! $isSuperAdmin
                    ? $resource->acknowledgments()->whereIn('business_id', $businessIds)
                        ->where('version', $resource->version)->latest('acknowledged_at')->first()
                    : null;
                $record['acknowledged_at'] = $acknowledgment?->acknowledged_at;
                $record['acknowledgment_required'] = (bool) $resource->requires_acknowledgment;

                return $this->withBusinessName($record, $businessNames);
            });

        $marketingQuery = MarketingActivity::query()
            ->where(function (Builder $query) use ($businessIds): void {
                $query->whereIn('business_id', $businessIds)->orWhere('scope', 'program');
            })
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at');
        if (! $isSuperAdmin) {
            $marketingQuery->whereIn('status', ['planned', 'scheduled', 'completed']);
        }
        $marketing = $marketingQuery
            ->get(['id', 'business_id', 'scope', 'title', 'description', 'member_description', 'promotion_type', 'platform', 'published_url', 'scheduled_at', 'completed_at', 'status'])
            ->map(function (MarketingActivity $activity) use ($businessNames, $isSuperAdmin): array {
                $record = $isSuperAdmin
                    ? $activity->toArray()
                    : $activity->only(['id', 'business_id', 'scope', 'title', 'member_description', 'promotion_type', 'platform', 'published_url', 'scheduled_at', 'completed_at', 'status']);

                return $this->withBusinessName($record, $businessNames);
            });
            $highlights = $marketing->where('scope', 'business')->values();
            $programActivities = $marketing->where('scope', 'program')->values();

        $businessData = $businesses->map(
            fn (Business $item): array => $this->businessData($item, $isSuperAdmin)
        );
        $membership = $business?->memberships->first();
        $activity = MemberActivity::query()
            ->with('business')
            ->where('user_id', $user->id)
            ->where(function (Builder $query) use ($businessIds): void {
                $query->whereNull('business_id')
                    ->orWhereIn('business_id', $businessIds);
            })
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (MemberActivity $item): array => [
                'id' => $item->id,
                'type' => $item->activity_type,
                'subject' => $item->subject_label,
                'business_name' => $item->business?->company_name,
                'created_at' => $item->created_at,
            ]);
        $participationQuery = EventRegistration::query()
            ->with(['event.business'])
            ->where('user_id', $user->id)
            ->whereHas('event', fn (Builder $query) => $query->whereIn('business_id', $businessIds));
        $participation = $participationQuery
            ->latest('registered_at')
            ->limit(30)
            ->get()
            ->map(fn (EventRegistration $registration): array => [
                'id' => $registration->id,
                'event_id' => $registration->event_id,
                'event' => $registration->event?->title,
                'business' => $registration->event?->business?->company_name,
                'starts_at' => $registration->event?->starts_at,
                'status' => $registration->status,
                'registered_at' => $registration->registered_at,
                'attended_at' => $registration->attended_at,
            ]);
        $engagementRegistrations = EventRegistration::query()
            ->where('user_id', $user->id)
            ->whereHas('event', fn (Builder $query) => $query
                ->whereIn('business_id', $businessIds));

        $attendanceHistory = MemberActivity::query()
            ->where('user_id', $user->id)
            ->where('activity_type', 'event_attended')
            ->whereIn('business_id', $businessIds)
            ->latest()
            ->limit(30)
            ->get(['id', 'event_id', 'business_id', 'subject_label', 'created_at'])
            ->map(fn (MemberActivity $item): array => [
                'id' => $item->id,
                'event_id' => $item->event_id,
                'event' => $item->subject_label,
                'recorded_at' => $item->created_at,
            ]);

        return response()->json([
            'user' => [
                'name' => $user->profile?->full_name ?? '',
                'first_name' => $user->profile?->first_name,
                'last_name' => $user->profile?->last_name,
                'phone' => $user->profile?->phone,
                'job_title' => $user->profile?->job_title,
                'address_line_1' => $user->profile?->address_line_1,
                'address_line_2' => $user->profile?->address_line_2,
                'city' => $user->profile?->city,
                'state' => $user->profile?->state,
                'postal_code' => $user->profile?->postal_code,
                'country' => $user->profile?->country,
                'email' => $user->email,
                'account_type' => $user->account_type,
                'is_super_admin' => $isSuperAdmin,
                'email_announcements_opt_in' => (bool) $user->profile?->email_announcements_opt_in,
            ],
            'business' => $business ? $this->businessData($business, false) : null,
            'businesses' => $businessData,
            'membership' => $membership ? $this->membershipData($membership) : null,
            'reports' => $reports,
            'events' => $events,
            'resources' => $resources,
            'marketing' => $marketing,
            'highlights' => $highlights,
            'program_activities' => $programActivities,
            'activity' => $activity,
            'participation' => $participation,
            'attendance_history' => $attendanceHistory,
            'engagement' => [
                'rsvps_30_days' => MemberActivity::query()
                    ->where('user_id', $user->id)
                    ->whereIn('business_id', $businessIds)
                    ->whereIn('activity_type', ['event_rsvp', 'event_registered'])
                    ->where('created_at', '>=', now()->subDays(30)->startOfDay())
                    ->count(),

                'upcoming_registered' => (clone $engagementRegistrations)
                    ->whereIn('status', ['registered', 'confirmed'])
                    ->whereHas('event', fn (Builder $query) => $query
                        ->where('starts_at', '>=', now()))
                    ->count(),

                'recorded_attendance' => (clone $engagementRegistrations)
                    ->whereNotNull('attended_at')
                    ->count(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function businessData(Business $business, bool $isSuperAdmin): array
    {
        $membership = $business->memberships->first();
        $category = $business->categories->firstWhere('pivot.is_primary', true)?->name
            ?? data_get($business->settings, 'category');

        return [
            'id' => $business->id,
            'name' => $business->company_name,
            'slug' => $business->slug,
            'tagline' => $business->tagline,
            'short_description' => $business->short_description,
            'description' => $business->description,
            'year_established' => $business->year_established,
            'employee_count' => $business->employee_count,
            'contact_name' => $business->contact_name,
            'contact_email' => $business->contact_email,
            'contact_phone' => $business->contact_phone,
            'alternate_phone' => $business->alternate_phone,
            'website' => $business->website,
            'facebook_url' => $business->facebook_url,
            'instagram_url' => $business->instagram_url,
            'linkedin_url' => $business->linkedin_url,
            'tiktok_url' => $business->tiktok_url,
            'youtube_url' => $business->youtube_url,
            'other_social_url' => $business->other_social_url,
            'address_line_1' => $business->address_line_1,
            'address_line_2' => $business->address_line_2,
            'city' => $business->city,
            'state' => $business->state,
            'postal_code' => $business->postal_code,
            'country' => $business->country,
            'category' => $category,
            'categories' => $business->categories->pluck('name')->values(),
            'specialties' => $business->specialties ?? [],
            'accessibility_information' => $business->accessibility_information,
            'appointment_methods' => $business->appointment_methods ?? [],
            'service_areas' => $business->service_areas ?? [],
            'images' => $business->images->map(fn ($image): array => [
                'id' => $image->id,
                'url' => route('business-media.images', $image),
                'alt_text' => $image->alt_text,
                'is_primary' => $image->is_primary,
            ])->values(),
            'image_count' => $business->images->count(),
            'is_published' => $business->is_published,
            'directory_status' => $business->is_published ? 'Published' : 'Private preview',
            'status' => $business->status,
            'is_active' => $business->is_active,
            'role' => $isSuperAdmin ? 'super_admin' : $business->pivot->role,
            'can_manage_profile' => ! $isSuperAdmin && (
                in_array($business->pivot->role, ['owner', 'admin'], true)
                || in_array($business->pivot->access_level, ['owner', 'admin'], true)
            ),
            'public_url' => $business->is_published && $business->directory_visibility === 'public'
                ? route('businesses.show', $business->slug)
                : null,
            'membership' => $membership ? $this->membershipData($membership) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipData(Membership $membership): array
    {
        return [
            'plan' => $membership->plan?->name,
            'status' => $membership->status,
            'payment_status' => $membership->payment_status,
            'payment_is_simulated' => data_get($membership->metadata, 'payment_mode') === 'simulated'
                || data_get($membership->metadata, 'payment_provider') === 'placeholder',
            'start_date' => $membership->start_date?->toDateString(),
            'end_date' => $membership->end_date?->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  Collection<int|string, string>  $businessNames
     * @return array<string, mixed>
     */
    private function withBusinessName(array $record, Collection $businessNames): array
    {
        $record['business_name'] = $record['business_id'] === null
            ? null
            : $businessNames->get($record['business_id']);

        return $record;
    }
}
