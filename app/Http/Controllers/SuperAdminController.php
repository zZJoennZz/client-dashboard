<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessMetric;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MarketingActivity;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Report;
use App\Models\Resource;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuperAdminController extends Controller
{
    public function __invoke(): View
    {
        return view('super-admin.index');
    }

    public function overview(): JsonResponse
    {
        $businesses = Business::query()->whereNull('deleted_at');
        $membershipsByStatus = Membership::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $activeOrders = Order::query()->whereIn('status', ['submitted', 'under_review', 'approved', 'in_progress']);
        $confirmedPayments = OrderPayment::query()
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->where('status', 'confirmed')
            ->groupBy('currency')
            ->get()
            ->map(fn ($record): array => ['currency' => $record->currency, 'amount' => (float) $record->total]);
        $confirmedPaymentsByOrder = DB::table('order_payments')
            ->select('order_id', 'currency')
            ->selectRaw('SUM(amount) as confirmed_total')
            ->where('status', 'confirmed')
            ->groupBy('order_id', 'currency');
        $outstandingBalance = DB::table('orders')
            ->leftJoinSub($confirmedPaymentsByOrder, 'confirmed_payments', function ($join): void {
                $join->on('confirmed_payments.order_id', '=', 'orders.id')
                    ->on('confirmed_payments.currency', '=', 'orders.currency');
            })
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNotIn('payment_status', ['paid', 'waived'])
            ->select('orders.currency')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.total > 0 THEN orders.total ELSE 0 END - COALESCE(confirmed_payments.confirmed_total, 0)), 0) as outstanding_balance')
            ->groupBy('orders.currency')
            ->get()
            ->map(fn ($record): array => ['currency' => $record->currency, 'amount' => (float) $record->outstanding_balance]);

        $recentMemberships = Membership::query()
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->orderBy('created_at')
            ->get(['created_at']);
        $membershipGrowth = collect(range(5, 0))->map(function (int $monthsAgo) use ($recentMemberships): array {
            $month = now()->startOfMonth()->subMonths($monthsAgo);

            return [
                'month' => $month->format('M Y'),
                'new_memberships' => $recentMemberships->filter(
                    fn (Membership $membership): bool => $membership->created_at?->isSameMonth($month) ?? false
                )->count(),
            ];
        })->values();

        $incompleteBusinesses = Business::query()
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereNull('description')->orWhere('description', '')
                    ->orWhereNull('contact_email')->orWhere('contact_email', '')
                    ->orWhereNull('website')->orWhere('website', '');
            })->count();

        return response()->json([
            'metrics' => [
                'businesses_total' => (clone $businesses)->count(),
                'businesses_published' => (clone $businesses)->where('is_published', true)->count(),
                'businesses_unpublished' => (clone $businesses)->where('is_published', false)->count(),
                'accounts_total' => User::query()->count(),
                'regular_accounts' => User::query()->where('account_type', User::ACCOUNT_TYPE_REGULAR)->count(),
                'super_admins' => User::query()->where('account_type', User::ACCOUNT_TYPE_SUPER_ADMIN)->count(),
                'new_accounts_30_days' => User::query()->where('created_at', '>=', now()->subDays(30))->count(),
                'incomplete_businesses' => $incompleteBusinesses,
                'upcoming_events_30_days' => Event::query()->where('starts_at', '>=', now())
                    ->where('starts_at', '<', now()->addDays(30))->where('is_published', true)->count(),
                'event_registrations' => EventRegistration::query()->whereIn('status', ['registered', 'confirmed'])->count(),
                'scheduled_promotions' => MarketingActivity::query()->where('status', 'scheduled')->count(),
                'completed_promotions_30_days' => MarketingActivity::query()->where('status', 'completed')
                    ->where('completed_at', '>=', now()->subDays(30))->count(),
                'pending_orders' => (clone $activeOrders)->count(),
                'confirmed_order_payments' => $confirmedPayments,
                'outstanding_order_balance' => $outstandingBalance,
                'reports_published' => Report::query()->where('status', 'published')->count(),
                'resources_published' => Resource::query()->where('status', 'published')->count(),
            ],
            'membership_statuses' => $membershipsByStatus,
            'membership_growth' => $membershipGrowth,
            'recent_audit_logs' => AuditLog::query()
                ->with(['actor.profile'])
                ->latest()
                ->limit(10)
                ->get(['id', 'user_id', 'action', 'model_type', 'model_id', 'created_at'])
                ->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'actor' => $log->actor?->profile?->full_name ?? $log->actor?->email ?? 'System',
                    'action' => $log->action,
                    'target_type' => class_basename($log->model_type),
                    'target_id' => $log->model_id,
                    'created_at' => $log->created_at,
                ]),
            'metric_definitions' => [
                'confirmed_order_payments' => 'Sum of recorded order_payments with status confirmed, grouped by currency. Does not include membership fees.',
                'outstanding_order_balance' => 'Non-draft, non-cancelled order totals less confirmed recorded payments, grouped by currency, for orders not marked paid or waived.',
                'incomplete_businesses' => 'Businesses missing a description, contact email, or website.',
                'membership_growth' => 'New membership records by creation month, not net member growth.',
            ],
        ]);
    }

    public function businesses(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'publication' => ['nullable', 'in:published,unpublished'],
            'active' => ['nullable', 'in:active,inactive'],
            'membership_status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'in:name,newest,oldest'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Business::query()
            ->whereNull('deleted_at')
            ->withCount(['users', 'images'])
            ->with(['memberships' => fn ($memberships) => $memberships->with('plan')->latest(), 'categories', 'images']);

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($businesses) => $businesses
                ->where('company_name', 'like', $search)
                ->orWhere('contact_name', 'like', $search)
                ->orWhere('contact_email', 'like', $search));
        }

        if (! empty($filters['publication'])) {
            $query->where('is_published', $filters['publication'] === 'published');
        }

        if (! empty($filters['active'])) {
            $query->where('is_active', $filters['active'] === 'active');
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['category'])) {
            $query->where('settings->category', $filters['category']);
        }

        if (! empty($filters['membership_status'])) {
            $query->whereHas('memberships', fn ($memberships) => $memberships->where('status', $filters['membership_status']));
        }

        $query->when(($filters['sort'] ?? 'name') === 'newest', fn ($businesses) => $businesses->orderByDesc('created_at'))
            ->when(($filters['sort'] ?? 'name') === 'oldest', fn ($businesses) => $businesses->orderBy('created_at'))
            ->when(($filters['sort'] ?? 'name') === 'name', fn ($businesses) => $businesses->orderBy('company_name'));
        $businesses = $query->paginate(20)->withQueryString();

        return response()->json([
            'data' => $businesses->getCollection()->map(fn (Business $business): array => [
                'id' => $business->id,
                'name' => $business->company_name,
                'slug' => $business->slug,
                'category' => $business->categories->firstWhere('pivot.is_primary', true)?->name
                    ?? data_get($business->settings, 'category'),
                'categories' => $business->categories->pluck('name'),
                'tagline' => $business->tagline,
                'short_description' => $business->short_description,
                'primary_image_url' => $business->images->firstWhere('is_primary', true)
                    ? route('business-media.images', $business->images->firstWhere('is_primary', true))
                    : null,
                'image_count' => $business->images_count,
                'contact_name' => $business->contact_name,
                'contact_email' => $business->contact_email,
                'city' => $business->city,
                'state' => $business->state,
                'status' => $business->status,
                'is_active' => $business->is_active,
                'is_featured' => $business->is_featured,
                'is_published' => $business->is_published,
                'directory_visibility' => $business->directory_visibility,
                'members_count' => $business->users_count,
                'membership' => $business->memberships->first() ? [
                    'plan' => $business->memberships->first()->plan?->name,
                    'status' => $business->memberships->first()->status,
                    'payment_status' => $business->memberships->first()->payment_status,
                    'start_date' => $business->memberships->first()->start_date?->toDateString(),
                ] : null,
            ]),
            'meta' => [
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
                'total' => $businesses->total(),
            ],
            'categories' => Business::query()
                ->whereNull('deleted_at')
                ->get(['settings'])
                ->map(fn (Business $business) => data_get($business->settings, 'category'))
                ->filter()
                ->unique()
                ->sort()
                ->values(),
        ]);
    }

    public function businessDetails(Business $business): JsonResponse
    {
        abort_if($business->deleted_at !== null, 404);

        $business->load(['memberships.plan', 'categories', 'images', 'video', 'hours', 'highlights']);
        $members = $business->businessUsers()
            ->where('status', 'active')
            ->with('user.profile')
            ->orderBy('id')
            ->get()
            ->map(fn ($association): array => [
                'id' => $association->user->id,
                'name' => $association->user->profile?->full_name ?? 'Profile incomplete',
                'email' => $association->user->email,
                'account_type' => $association->user->account_type,
                'role' => $association->role,
            ]);

        return response()->json([
            'business' => [
                'id' => $business->id,
                'name' => $business->company_name,
                'slug' => $business->slug,
                'description' => $business->description,
                'category' => $business->categories->firstWhere('pivot.is_primary', true)?->name
                    ?? data_get($business->settings, 'category'),
                'categories' => $business->categories->map(fn ($category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'is_primary' => (bool) $category->pivot->is_primary,
                ]),
                'tagline' => $business->tagline,
                'short_description' => $business->short_description,
                'year_established' => $business->year_established,
                'employee_count' => $business->employee_count,
                'ownership_information' => $business->ownership_information,
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
                'latitude' => $business->latitude,
                'longitude' => $business->longitude,
                'specialties' => $business->specialties ?? [],
                'accessibility_information' => $business->accessibility_information,
                'appointment_methods' => $business->appointment_methods ?? [],
                'service_areas' => $business->service_areas ?? [],
                'meta_title' => $business->meta_title,
                'meta_description' => $business->meta_description,
                'hours' => $business->hours->map(fn ($hour): array => [
                    'day_of_week' => $hour->day_of_week,
                    'opens_at' => $hour->opens_at ? substr($hour->opens_at, 0, 5) : null,
                    'closes_at' => $hour->closes_at ? substr($hour->closes_at, 0, 5) : null,
                    'is_closed' => $hour->is_closed,
                    'by_appointment' => $hour->by_appointment,
                ]),
                'highlights' => $business->highlights->map(fn ($highlight): array => [
                    'id' => $highlight->id,
                    'name' => $highlight->name,
                    'description' => $highlight->description,
                    'price_information' => $highlight->price_information,
                    'is_active' => $highlight->is_active,
                ]),
                'images' => $business->images->map(fn ($image): array => [
                    'id' => $image->id,
                    'url' => route('business-media.images', $image),
                    'alt_text' => $image->alt_text,
                    'sort_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                ]),
                'video' => $business->video ? [
                    'source_type' => $business->video->source_type,
                    'source_url' => $business->video->source_url,
                    'video_url' => $business->video->source_type === 'upload'
                        ? route('business-media.videos', $business->video)
                        : null,
                ] : null,
                'status' => $business->status,
                'is_active' => $business->is_active,
                'is_featured' => $business->is_featured,
                'is_published' => $business->is_published,
                'directory_visibility' => $business->directory_visibility,
                'created_at' => $business->created_at,
                'members' => $members,
                'memberships' => $business->memberships->map(fn ($membership): array => [
                    'id' => $membership->id,
                    'plan' => $membership->plan?->name,
                    'status' => $membership->status,
                    'payment_status' => $membership->payment_status,
                    'payment_is_simulated' => data_get($membership->metadata, 'payment_mode') === 'simulated'
                        || data_get($membership->metadata, 'payment_provider') === 'placeholder',
                    'start_date' => $membership->start_date?->toDateString(),
                    'end_date' => $membership->end_date?->toDateString(),
                ]),
                'activity' => [
                    'reports' => Report::query()->where('business_id', $business->id)->count(),
                    'events' => Event::query()->where('business_id', $business->id)->count(),
                    'marketing_activities' => MarketingActivity::query()->where('business_id', $business->id)->count(),
                    'orders' => Order::query()->where('business_id', $business->id)->count(),
                ],
            ],
        ]);
    }

    public function membershipDetails(Membership $membership): JsonResponse
    {
        $membership->load(['business.businessUsers.user.profile', 'plan']);
        $history = AuditLog::query()
            ->where('model_type', $membership->getMorphClass())
            ->where('model_id', $membership->id)
            ->with('actor.profile')
            ->latest()
            ->get(['id', 'user_id', 'action', 'old_values', 'new_values', 'created_at'])
            ->map(fn (AuditLog $log): array => [
                'action' => $log->action,
                'actor' => $log->actor?->profile?->full_name ?? $log->actor?->email ?? 'System',
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'created_at' => $log->created_at,
            ]);

        return response()->json([
            'id' => $membership->id,
            'business' => $membership->business?->company_name,
            'plan' => $membership->plan?->name,
            'status' => $membership->status,
            'payment_status' => $membership->payment_status,
            'payment_is_simulated' => data_get($membership->metadata, 'payment_mode') === 'simulated'
                || data_get($membership->metadata, 'payment_provider') === 'placeholder',
            'start_date' => $membership->start_date?->toDateString(),
            'renewal_date' => $membership->end_date?->toDateString(),
            'members' => $membership->business?->businessUsers
                ->where('status', 'active')
                ->map(fn ($association): array => [
                    'name' => $association->user?->profile?->full_name,
                    'email' => $association->user?->email,
                    'role' => $association->role,
                ])->values() ?? [],
            'history' => $history,
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'account_type' => ['nullable', 'in:regular,super_admin'],
            'business_id' => ['nullable', 'integer', 'exists:clients,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = User::query()->with(['profile', 'businesses' => fn ($businesses) => $businesses
            ->wherePivot('status', 'active')
            ->select('clients.id', 'company_name')]);

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($users) use ($search): void {
                $users->where('email', 'like', $search)
                    ->orWhereHas('profile', fn ($profiles) => $profiles
                        ->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search));
            });
        }

        if (! empty($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        if (! empty($filters['business_id'])) {
            $query->whereHas('businesses', fn ($businesses) => $businesses
                ->whereKey($filters['business_id'])
                ->where('business_user.status', 'active'));
        }

        $accounts = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return response()->json([
            'data' => $accounts->getCollection()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->profile?->full_name ?? 'Profile incomplete',
                'email' => $user->email,
                'account_type' => $user->account_type,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
                'businesses' => $user->businesses->map(fn (Business $business): array => [
                    'id' => $business->id,
                    'name' => $business->company_name,
                ]),
            ]),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'total' => $accounts->total(),
            ],
        ]);
    }

    public function workspaceData(Request $request, string $section): JsonResponse
    {
        $query = null;
        $mapper = null;

        switch ($section) {
            case 'memberships':
                $query = Membership::query()->with(['business', 'plan'])->latest();
                $mapper = fn (Membership $membership): array => [
                    'id' => $membership->id,
                    'business_id' => $membership->business_id,
                    'business_name' => $membership->business?->company_name ?? 'Deleted business',
                    'plan' => $membership->plan?->name,
                    'status' => $membership->status,
                    'payment_status' => $membership->payment_status,
                    'start_date' => $membership->start_date?->toDateString(),
                    'end_date' => $membership->end_date?->toDateString(),
                    'payment_is_simulated' => data_get($membership->metadata, 'payment_mode') === 'simulated'
                        || data_get($membership->metadata, 'payment_provider') === 'placeholder',
                ];
                break;

            case 'content':
                $query = Resource::query()->with('business')->latest();
                $mapper = fn (Resource $resource): array => [
                    'id' => $resource->id,
                    'business_id' => $resource->business_id,
                    'title' => $resource->title,
                    'description' => $resource->description,
                    'business_name' => $resource->business?->company_name ?? 'Shared resource',
                    'resource_type' => $resource->resource_type,
                    'status' => $resource->status,
                    'visibility' => $resource->visibility,
                    'is_featured' => $resource->is_featured,
                    'file_name' => $resource->file_name,
                    'published_at' => $resource->published_at?->toDateString(),
                ];
                break;

            case 'reports':
                $query = Report::query()->with('business')->latest('created_at');
                $mapper = fn (Report $report): array => [
                    'id' => $report->id,
                    'business_id' => $report->business_id,
                    'title' => $report->title,
                    'description' => $report->description,
                    'business_name' => $report->business?->company_name ?? 'Deleted business',
                    'reporting_period' => $report->reporting_period,
                    'status' => $report->status,
                    'visibility' => $report->visibility,
                    'file_name' => $report->file_name,
                    'published_at' => $report->published_at?->toDateString(),
                ];
                break;

            case 'events':
                $query = Event::query()
                    ->with('business')
                    ->withCount([
                        'registrations as active_registrations_count' => fn (Builder $registrations) => $registrations
                            ->whereIn('status', ['registered', 'confirmed']),
                    ])
                    ->orderByDesc('starts_at');
                $mapper = fn (Event $event): array => [
                    'id' => $event->id,
                    'business_id' => $event->business_id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'location' => $event->location,
                    'meeting_url' => $event->meeting_url,
                    'business_name' => $event->business?->company_name ?? 'Deleted business',
                    'status' => $event->status,
                    'is_published' => $event->is_published,
                    'starts_at' => $event->starts_at?->toDateTimeString(),
                    'ends_at' => $event->ends_at?->toDateTimeString(),
                    'capacity' => $event->capacity,
                    'registration_enabled' => $event->registration_enabled,
                    'registration_deadline' => $event->registration_deadline?->toDateTimeString(),
                    'active_registrations' => $event->active_registrations_count,
                ];
                break;

            case 'marketing':
                $query = MarketingActivity::query()->with(['business', 'responsibleAdmin.profile'])->latest('scheduled_at');
                $mapper = fn (MarketingActivity $activity): array => [
                    'id' => $activity->id,
                    'title' => $activity->title,
                    'business_name' => $activity->business?->company_name ?? 'Deleted business',
                    'status' => $activity->status,
                    'promotion_type' => $activity->promotion_type,
                    'platform' => $activity->platform,
                    'responsible_admin_id' => $activity->responsible_admin_id,
                    'scheduled_at' => $activity->scheduled_at?->toDateTimeString(),
                    'completed_at' => $activity->completed_at?->toDateTimeString(),
                    'assigned_staff' => $activity->responsibleAdmin?->profile?->full_name,
                    'description' => $activity->description,
                    'member_description' => $activity->member_description,
                    'caption' => $activity->caption,
                    'notes' => $activity->notes,
                    'published_url' => $activity->published_url,
                ];
                break;

            case 'orders':
                $query = Order::query()->with(['business', 'user.profile', 'assignedStaff.profile', 'items', 'payments'])->latest();
                $mapper = fn (Order $order): array => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'business_name' => $order->business?->company_name ?? 'Deleted business',
                    'member_name' => $order->user?->profile?->full_name,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'total' => $order->total,
                    'currency' => $order->currency,
                    'submitted_at' => $order->submitted_at?->toDateTimeString(),
                    'assigned_staff_id' => $order->assigned_staff_id,
                    'assigned_staff' => $order->assignedStaff?->profile?->full_name,
                    'fulfillment_notes' => $order->fulfillment_notes,
                    'items' => $order->items->map(fn (OrderItem $item): array => [
                        'service_name' => $item->service_name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->line_total,
                    ]),
                ];
                break;

            case 'services':
                $query = Service::withTrashed()->orderBy('name');
                $mapper = fn (Service $service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'slug' => $service->slug,
                    'description' => $service->description,
                    'price' => $service->price,
                    'currency' => $service->currency,
                    'status' => $service->status,
                    'is_featured' => $service->is_featured,
                    'featured' => $service->is_featured,
                    'deleted' => $service->trashed(),
                ];
                break;

            case 'billing':
                $query = OrderPayment::query()->with(['order.business', 'recorder.profile'])
                    ->latest('payment_date');
                $mapper = fn (OrderPayment $payment): array => [
                    'id' => $payment->id,
                    'order_number' => $payment->order?->order_number,
                    'business_name' => $payment->order?->business?->company_name ?? 'Deleted business',
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'method' => $payment->method,
                    'reference' => $payment->reference,
                    'status' => $payment->status,
                    'recorded_by' => $payment->recorder?->profile?->full_name,
                ];
                break;

            case 'analytics':
                $query = BusinessMetric::query()->with('business')->latest('period_start');
                $mapper = fn (BusinessMetric $metric): array => [
                    'id' => $metric->id,
                    'business_name' => $metric->business?->company_name ?? 'Deleted business',
                    'metric_type' => $metric->metric_type,
                    'value' => $metric->value,
                    'unit' => $metric->unit,
                    'period_start' => $metric->period_start?->toDateString(),
                    'period_end' => $metric->period_end?->toDateString(),
                    'source' => $metric->source,
                    'recorded_at' => $metric->recorded_at?->toDateTimeString(),
                ];
                break;

            case 'audit':
                $query = AuditLog::query()->with('actor.profile')->latest();
                $mapper = fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'actor' => $log->actor?->profile?->full_name ?? $log->actor?->email ?? 'System',
                    'action' => $log->action,
                    'record_type' => class_basename($log->model_type),
                    'record_id' => $log->model_id,
                    'created_at' => $log->created_at?->toDateTimeString(),
                ];
                break;
        }

        abort_if($query === null || $mapper === null, 404);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:60'],
            'payment_status' => ['nullable', 'string', 'max:60'],
            'business_id' => ['nullable', 'integer', 'exists:clients,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if (! empty($filters['status'])) {
            match ($section) {
                'billing' => $query->where('status', $filters['status']),
                'services' => $query->where('status', $filters['status']),
                default => $query->where('status', $filters['status']),
            };
        }
        if (! empty($filters['payment_status']) && $section === 'orders') {
            $query->where('payment_status', $filters['payment_status']);
        }
        if (! empty($filters['business_id'])) {
            match ($section) {
                'billing' => $query->whereHas('order', fn (Builder $orders) => $orders->where('business_id', $filters['business_id'])),
                'audit', 'services' => null,
                default => $query->where('business_id', $filters['business_id']),
            };
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            match ($section) {
                'memberships' => $query->whereHas('business', fn (Builder $businesses) => $businesses->where('company_name', 'like', $search)),
                'events', 'marketing', 'reports', 'content' => $query->where('title', 'like', $search),
                'orders' => $query->where('order_number', 'like', $search),
                'services' => $query->where('name', 'like', $search),
                'billing' => $query->whereHas('order', fn (Builder $orders) => $orders->where('order_number', 'like', $search)),
                'analytics' => $query->where('metric_type', 'like', $search),
                'audit' => $query->where('action', 'like', $search),
                default => null,
            };
        }
        if (! empty($filters['from'])) {
            $dateColumn = match ($section) {
                'events' => 'starts_at',
                'marketing' => 'scheduled_at',
                'billing' => 'payment_date',
                'analytics' => 'period_start',
                default => 'created_at',
            };
            $query->whereDate($dateColumn, '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $dateColumn = match ($section) {
                'events' => 'starts_at',
                'marketing' => 'scheduled_at',
                'billing' => 'payment_date',
                'analytics' => 'period_start',
                default => 'created_at',
            };
            $query->whereDate($dateColumn, '<=', $filters['to']);
        }

        $paginator = $query->paginate(25)->withQueryString();

        return response()->json([
            'data' => $paginator->getCollection()->map($mapper)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'status_options' => $section === 'memberships'
                ? Membership::query()->distinct()->orderBy('status')->pluck('status')->values()
                : [],
        ]);
    }

    public function updateBusinessPublication(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'is_published' => ['required', 'boolean'],
        ]);

        if ($business->deleted_at !== null) {
            throw ValidationException::withMessages(['business' => 'Deleted businesses cannot be published.']);
        }
        if ((bool) $validated['is_published']) {
            $business->loadMissing('categories');
            if (blank($business->company_name) || $business->categories->isEmpty()) {
                throw ValidationException::withMessages(['business' => 'A business name and at least one category are required before publishing.']);
            }
            if ($business->images()->count() < 2) {
                throw ValidationException::withMessages(['images' => 'Add at least two valid listing images before publishing.']);
            }
        }

        $old = ['is_published' => $business->is_published, 'directory_visibility' => $business->directory_visibility];
        $published = (bool) $validated['is_published'];

        DB::transaction(function () use ($business, $published, $old, $request): void {
            $business->forceFill([
                'is_published' => $published,
                'directory_visibility' => $published ? 'public' : 'private',
                'published_at' => $published ? ($business->published_at ?? now()) : null,
            ])->save();

            AuditLog::record(
                $request->user(),
                $published ? 'business.published' : 'business.unpublished',
                $business,
                $old,
                ['is_published' => $published, 'directory_visibility' => $business->directory_visibility],
            );
        });

        return response()->json([
            'id' => $business->id,
            'is_published' => $business->is_published,
            'directory_visibility' => $business->directory_visibility,
        ]);
    }
}
