<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\MemberActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SuperAdminEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $event = DB::transaction(function () use ($request, $data): Event {
            $event = Event::query()->create([
                ...$data,
                'created_by' => $request->user()->id,
            ]);
            AuditLog::record($request->user(), 'event.created', $event, [], $event->only([
                'business_id', 'title', 'status', 'is_published', 'starts_at', 'ends_at', 'capacity', 'registration_enabled',
            ]));

            return $event;
        });

        return response()->json(['id' => $event->id], 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($event, $data, $request): void {
            $old = $event->only(['business_id', 'title', 'status', 'is_published', 'starts_at', 'ends_at', 'capacity', 'registration_enabled']);
            if (($data['capacity'] ?? null) !== null && $data['capacity'] < $event->registrations()->whereIn('status', ['registered', 'confirmed'])->count()) {
                throw ValidationException::withMessages(['capacity' => 'Capacity cannot be lower than the number of active registrants.']);
            }
            $event->fill($data)->save();
            AuditLog::record($request->user(), 'event.updated', $event, $old, $event->only(array_keys($old)));
        });

        return response()->json(['id' => $event->id, 'status' => $event->status]);
    }

    public function setStatus(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'published', 'cancelled'])]]);
        $old = $event->only(['status', 'is_published']);
        $event->forceFill([
            'status' => $data['status'],
            'is_published' => $data['status'] === 'published',
        ])->save();
        AuditLog::record($request->user(), 'event.status_changed', $event, $old, $event->only(['status', 'is_published']));

        return response()->json(['id' => $event->id, 'status' => $event->status, 'is_published' => $event->is_published]);
    }

    public function registrations(Event $event): JsonResponse
    {
        return response()->json([
            'event' => ['id' => $event->id, 'title' => $event->title, 'capacity' => $event->capacity],
            'data' => $event->registrations()
                ->with(['user.profile', 'user.businesses'])
                ->orderBy('registered_at')
                ->paginate(50)
                ->through(fn (EventRegistration $registration): array => [
                    'id' => $registration->id,
                    'user_id' => $registration->user_id,
                    'name' => $registration->user?->profile?->full_name ?? 'Profile incomplete',
                    'email' => $registration->user?->email,
                    'businesses' => $registration->user?->businesses?->pluck('company_name')->values() ?? [],
                    'status' => $registration->status,
                    'registered_at' => $registration->registered_at?->toDateTimeString(),
                    'attended_at' => $registration->attended_at?->toDateTimeString(),
                ]),
        ]);
    }

    public function updateRegistration(Request $request, EventRegistration $registration): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['registered', 'confirmed', 'cancelled', 'attended'])],
        ]);
        $registration = DB::transaction(function () use ($request, $registration, $data): EventRegistration {
            $registration = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            $wasAttended = $registration->attended_at !== null;
            $old = ['status' => $registration->status, 'attended_at' => $registration->attended_at];
            $registration->forceFill([
                'status' => $data['status'],
                'cancelled_at' => $data['status'] === 'cancelled' ? now() : null,
                'attended_at' => $data['status'] === 'attended' ? ($registration->attended_at ?? now()) : $registration->attended_at,
            ])->save();
            if ($data['status'] === 'attended' && ! $wasAttended) {
                $registration->loadMissing('event');
                app(MemberActivityRecorder::class)->record(
                    $registration->user,
                    'event_attended',
                    $registration->event?->business_id,
                    'event_registration',
                    $registration->id,
                    $registration->event?->title,
                    $registration->event_id,
                );
            }
            AuditLog::record($request->user(), 'event.registration_updated', $registration, $old, [
                'status' => $registration->status,
                'attended_at' => $registration->attended_at,
            ]);

            return $registration;
        });

        return response()->json(['id' => $registration->id, 'status' => $registration->status]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url', 'max:2048'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(['draft', 'scheduled', 'published', 'cancelled'])],
            'is_published' => ['required', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'registration_enabled' => ['required', 'boolean'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:starts_at'],
        ]);
        if (in_array($data['status'], ['draft', 'cancelled'], true) && $data['is_published']) {
            throw ValidationException::withMessages(['is_published' => 'Draft and cancelled events cannot be published.']);
        }
        if ($data['status'] === 'published' && ! $data['is_published']) {
            throw ValidationException::withMessages(['is_published' => 'Published events must be marked as published.']);
        }

        return $data;
    }
}
