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

class MemberEventController extends Controller
{
    public function storeEvent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedEventData($request, true);

        $business = Business::query()
            ->whereKey($data['business_id'])
            ->whereNull('deleted_at')
            ->firstOrFail();
        $this->assertCanManageBusiness($user, $business);

        $event = DB::transaction(function () use ($business, $data, $user): Event {
            $event = $business->events()->create([
                ...$data,
                'status' => 'draft',
                'is_published' => false,
                'created_by' => $user->id,
            ]);
            AuditLog::record($user, 'event.created', $event, [], $event->only([
                'business_id', 'title', 'status', 'is_published', 'starts_at', 'ends_at', 'capacity', 'registration_enabled',
            ]));

            return $event;
        });

        return response()->json(['id' => $event->id, 'status' => $event->status], 201);
    }

    public function updateEvent(Request $request, Event $event): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanManageBusiness($user, $event->business);
        abort_unless(in_array($event->status, ['draft', 'scheduled', 'published'], true), 409);

        $data = $this->validatedEventData($request, false);
        if ($data['capacity'] !== null && $data['capacity'] < $event->registrations()->whereIn('status', ['registered', 'confirmed'])->count()) {
            throw ValidationException::withMessages([
                'capacity' => 'Capacity cannot be lower than the number of active registrants.',
            ]);
        }

        $event = DB::transaction(function () use ($event, $data, $request, $user): Event {
            $old = $event->only([
                'business_id', 'title', 'description', 'location', 'meeting_url', 'starts_at', 'ends_at',
                'registration_deadline', 'capacity', 'registration_enabled', 'status', 'is_published',
            ]);
            $event->fill([
                ...$data,
                'status' => 'draft',
                'is_published' => false,
            ])->save();
            AuditLog::record($user, 'event.updated', $event, $old, $event->only(array_keys($old)));

            return $event;
        });

        return response()->json(['id' => $event->id, 'status' => $event->status]);
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $registration = DB::transaction(function () use ($event, $user): EventRegistration {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->assertCanRegister($lockedEvent, $user);

            $existing = EventRegistration::withTrashed()
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing && $existing->isActive()) {
                throw ValidationException::withMessages(['event' => 'You are already registered for this event.']);
            }

            $activeCount = $lockedEvent->registrations()->whereIn('status', ['registered', 'confirmed'])->count();
            if ($lockedEvent->capacity !== null && $activeCount >= $lockedEvent->capacity) {
                throw ValidationException::withMessages(['event' => 'This event has reached capacity.']);
            }

            if ($existing) {
                $existing->restore();
                $existing->forceFill([
                    'status' => 'registered',
                    'registered_at' => now(),
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                ])->save();

                $registration = $existing;
            } else {
                $registration = $lockedEvent->registrations()->create([
                    'user_id' => $user->id,
                    'status' => 'registered',
                    'registered_at' => now(),
                ]);
            }

            app(MemberActivityRecorder::class)->record(
                $user,
                'event_rsvp',
                $lockedEvent->business_id,
                'event_registration',
                $registration->id,
                $lockedEvent->title,
                $lockedEvent->id,
            );

            return $registration;
        });

        return response()->json(['id' => $registration->id, 'status' => $registration->status], 201);
    }

    public function cancel(Request $request, EventRegistration $registration): JsonResponse
    {
        $registration = DB::transaction(function () use ($request, $registration): EventRegistration {
            $registration = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            abort_unless($registration->user_id === $request->user()->id, 404);
            if (! $registration->isActive()) {
                throw ValidationException::withMessages(['registration' => 'This registration is no longer active.']);
            }

            $registration->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();
            $registration->loadMissing('event');
            app(MemberActivityRecorder::class)->record(
                $request->user(),
                'event_cancelled',
                $registration->event?->business_id,
                'event_registration',
                $registration->id,
                $registration->event?->title,
                $registration->event_id,
            );

            return $registration;
        });

        return response()->json(['id' => $registration->id, 'status' => $registration->status]);
    }

    private function assertCanRegister(Event $event, User $user): void
    {
        if (! $event->is_published || ! $event->registration_enabled || $event->status === 'cancelled') {
            throw ValidationException::withMessages(['event' => 'Registration is not available for this event.']);
        }
        if ($event->registration_deadline && $event->registration_deadline->isPast()) {
            throw ValidationException::withMessages(['event' => 'The registration deadline has passed.']);
        }
        if (! $user->businessAssociations()->where('status', 'active')->exists()) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedEventData(Request $request, bool $creating): array
    {
        return $request->validate([
            'business_id' => $creating
                ? ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')]
                : ['prohibited'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url', 'max:2048'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'registration_enabled' => ['required', 'boolean'],
        ]);
    }

    private function assertCanManageBusiness(User $user, Business $business): void
    {
        abort_if($business->deleted_at !== null, 404);

        $association = $user->businessAssociations()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->first();
        abort_unless($association, 404);
        abort_unless(
            in_array($association->role, ['owner', 'admin'], true)
            || in_array($association->access_level, ['owner', 'admin'], true),
            403
        );
    }
}
