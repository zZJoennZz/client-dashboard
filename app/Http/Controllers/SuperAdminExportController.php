<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MarketingActivity;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminExportController extends Controller
{
    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['businesses', 'members', 'memberships', 'registrants', 'marketing', 'orders', 'payments'], true), 404);

        $filters = $request->validate([
            'business_id' => ['nullable', 'integer', 'exists:clients,id'],
            'status' => ['nullable', 'string', 'max:60'],
            'payment_status' => ['nullable', 'string', 'max:60'],
            'search' => ['nullable', 'string', 'max:150'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'publication' => ['nullable', Rule::in(['published', 'unpublished'])],
            'active' => ['nullable', Rule::in(['active', 'inactive'])],
            'membership_status' => ['nullable', 'string', 'max:60'],
            'sort' => ['nullable', Rule::in(['name', 'newest', 'oldest'])],
        ]);

        [$filename, $headers, $rows] = match ($type) {
            'businesses' => $this->businesses($filters),
            'members' => $this->members($filters),
            'memberships' => $this->memberships($filters),
            'registrants' => $this->registrants($filters),
            'marketing' => $this->marketing($filters),
            'orders' => $this->orders($filters),
            'payments' => $this->payments($filters),
        };

        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                fputcsv($output, array_map(fn ($value) => $this->safeCell($value), $row));
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function businesses(array $filters): array
    {
        $query = Business::query()->whereNull('deleted_at');
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('company_name', 'like', $search)->orWhere('contact_email', 'like', $search));
        }
        if (! empty($filters['business_id'])) $query->whereKey($filters['business_id']);
        if (! empty($filters['category'])) $query->where('settings->category', $filters['category']);
        if (isset($filters['publication'])) $query->where('is_published', $filters['publication'] === 'published');
        if (isset($filters['active'])) $query->where('is_active', $filters['active'] === 'active');
        if (! empty($filters['membership_status'])) {
            $query->whereHas('memberships', fn ($memberships) => $memberships->where('status', $filters['membership_status']));
        }
        if (! empty($filters['from'])) $query->whereDate('created_at', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('created_at', '<=', $filters['to']);
        $query->when(($filters['sort'] ?? 'name') === 'newest', fn ($businesses) => $businesses->orderByDesc('created_at'))
            ->when(($filters['sort'] ?? 'name') === 'oldest', fn ($businesses) => $businesses->orderBy('created_at'))
            ->when(($filters['sort'] ?? 'name') === 'name', fn ($businesses) => $businesses->orderBy('company_name'));
        $rows = $query->get()->map(fn (Business $business): array => [
            $business->id, $business->company_name, data_get($business->settings, 'category'),
            $business->contact_name, $business->contact_email, $business->contact_phone,
            $business->city, $business->state, $business->is_active ? 'active' : 'inactive',
            $business->is_published ? 'published' : 'unpublished', $business->created_at?->toDateString(),
        ])->all();

        return ['businesses.csv', ['id', 'business', 'category', 'contact', 'email', 'phone', 'city', 'state', 'business_status', 'directory_status', 'created_at'], $rows];
    }

    private function members(array $filters): array
    {
        $query = User::query()->where('account_type', User::ACCOUNT_TYPE_REGULAR)
            ->with(['profile', 'businesses' => fn ($q) => $q->wherePivot('status', 'active')]);
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('email', 'like', $search)
                ->orWhereHas('profile', fn ($p) => $p->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search)));
        }
        if (! empty($filters['business_id'])) {
            $query->whereHas('businesses', fn ($q) => $q->where('clients.id', $filters['business_id'])->where('business_user.status', 'active'));
        }
        if (! empty($filters['from'])) $query->whereDate('created_at', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('created_at', '<=', $filters['to']);
        $rows = $query->orderBy('id')->get()->flatMap(function (User $user): array {
            if ($user->businesses->isEmpty()) {
                return [[$user->id, $user->profile?->full_name, $user->email, null, $user->created_at?->toDateString()]];
            }

            return $user->businesses->map(fn (Business $business): array => [
                $user->id, $user->profile?->full_name, $user->email, $business->company_name, $user->created_at?->toDateString(),
            ])->all();
        })->all();

        return ['members.csv', ['user_id', 'member_name', 'email', 'business', 'joined_at'], $rows];
    }

    private function memberships(array $filters): array
    {
        $query = Membership::query()->with(['business', 'plan']);
        if (! empty($filters['business_id'])) $query->where('business_id', $filters['business_id']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['from'])) $query->whereDate('created_at', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('created_at', '<=', $filters['to']);
        $rows = $query->orderBy('id')->get()->map(fn (Membership $membership): array => [
            $membership->id, $membership->business?->company_name, $membership->plan?->name,
            $membership->status, $membership->payment_status, $membership->start_date?->toDateString(),
            $membership->end_date?->toDateString(),
            data_get($membership->metadata, 'payment_mode') === 'simulated'
                || data_get($membership->metadata, 'payment_provider') === 'placeholder' ? 'simulated_placeholder_not_a_transaction' : '',
        ])->all();

        return ['memberships.csv', ['id', 'business', 'plan', 'membership_status', 'payment_status', 'start_date', 'renewal_date', 'payment_placeholder_note'], $rows];
    }

    private function registrants(array $filters): array
    {
        $query = EventRegistration::query()->with(['event.business', 'user.profile', 'user.businesses']);
        if (! empty($filters['event_id'])) $query->where('event_id', $filters['event_id']);
        if (! empty($filters['business_id'])) $query->whereHas('event', fn ($q) => $q->where('business_id', $filters['business_id']));
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['from'])) $query->whereDate('registered_at', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('registered_at', '<=', $filters['to']);
        $rows = $query->orderBy('id')->get()->map(fn (EventRegistration $registration): array => [
            $registration->event?->title, $registration->event?->business?->company_name,
            $registration->user?->profile?->full_name, $registration->user?->email,
            $registration->user?->businesses?->pluck('company_name')->join(', '),
            $registration->status, $registration->registered_at?->toDateTimeString(),
            $registration->attended_at?->toDateTimeString(),
        ])->all();

        return ['event-registrants.csv', ['event', 'business', 'participant', 'email', 'business_associations', 'status', 'registered_at', 'attended_at'], $rows];
    }

    private function marketing(array $filters): array
    {
        $query = MarketingActivity::query()->with(['business', 'responsibleAdmin.profile']);
        $this->filterBusinessAndDate($query, $filters, 'scheduled_at');
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['search'])) $query->where('title', 'like', '%'.$filters['search'].'%');
        $rows = $query->orderBy('id')->get()->map(fn (MarketingActivity $activity): array => [
            $activity->business?->company_name, $activity->title, $activity->promotion_type, $activity->platform,
            $activity->status, $activity->scheduled_at?->toDateTimeString(), $activity->completed_at?->toDateTimeString(),
            $activity->responsibleAdmin?->profile?->full_name, $activity->published_url,
        ])->all();

        return ['marketing-activities.csv', ['business', 'title', 'type', 'platform', 'status', 'scheduled_at', 'completed_at', 'assigned_staff', 'published_url'], $rows];
    }

    private function orders(array $filters): array
    {
        $query = Order::query()->with(['business', 'user.profile', 'items']);
        $this->filterBusinessAndDate($query, $filters);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['payment_status'])) $query->where('payment_status', $filters['payment_status']);
        if (! empty($filters['search'])) $query->where('order_number', 'like', '%'.$filters['search'].'%');
        $rows = $query->orderBy('id')->get()->flatMap(fn (Order $order) => $order->items->map(fn ($item): array => [
            $order->order_number, $order->business?->company_name, $order->user?->profile?->full_name,
            $order->status, $order->payment_status, $order->currency, $item->service_name,
            $item->quantity, $item->unit_price, $item->line_total, $order->submitted_at?->toDateTimeString(),
        ]))->all();

        return ['premium-service-orders.csv', ['order_number', 'business', 'member', 'status', 'payment_status', 'currency', 'service', 'quantity', 'unit_price_snapshot', 'line_total_snapshot', 'submitted_at'], $rows];
    }

    private function payments(array $filters): array
    {
        $query = OrderPayment::query()->with(['order.business', 'recorder.profile']);
        if (! empty($filters['business_id'])) $query->whereHas('order', fn ($q) => $q->where('business_id', $filters['business_id']));
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['search'])) $query->whereHas('order', fn ($q) => $q->where('order_number', 'like', '%'.$filters['search'].'%'));
        if (! empty($filters['from'])) $query->whereDate('payment_date', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('payment_date', '<=', $filters['to']);
        $rows = $query->orderBy('id')->get()->map(fn (OrderPayment $payment): array => [
            $payment->order?->order_number, $payment->order?->business?->company_name,
            $payment->amount, $payment->currency, $payment->payment_date?->toDateString(),
            $payment->method, $payment->reference, $payment->status, $payment->recorder?->profile?->full_name,
        ])->all();

        return ['recorded-order-payments.csv', ['order_number', 'business', 'amount', 'currency', 'payment_date', 'method', 'reference', 'status', 'recorded_by'], $rows];
    }

    private function filterBusinessAndDate($query, array $filters, string $dateColumn = 'created_at'): void
    {
        if (! empty($filters['business_id'])) $query->where('business_id', $filters['business_id']);
        if (! empty($filters['from'])) $query->whereDate($dateColumn, '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate($dateColumn, '<=', $filters['to']);
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if (preg_match('/^[\s]*[=+\-@]/u', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
