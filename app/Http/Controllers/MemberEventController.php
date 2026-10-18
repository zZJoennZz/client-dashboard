<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\MemberActivityRecorder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberEventController extends Controller
{
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
        if (! $user->businesses()
            ->whereKey($event->business_id)
            ->wherePivot('status', 'active')
            ->exists()) {
            abort(404);
        }
    }
}
