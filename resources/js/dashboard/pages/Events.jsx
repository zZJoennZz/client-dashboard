import React, { useEffect, useState } from 'react';
import { Card, EmptyState, PageHeading } from '../components/UI';
import SearchableBusinessSelect from '../../components/SearchableBusinessSelect';

function toDateTimeLocal(value) {
    if (!value) return '';
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

export default function Events({ data, onRefresh }) {
    const manageableBusinesses = data.event_business_options ?? [];
    const [events, setEvents] = useState(data.events);
    const [busy, setBusy] = useState(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [creating, setCreating] = useState(false);
    const [editingEvent, setEditingEvent] = useState(null);
    const [draft, setDraft] = useState({
        business_id: manageableBusinesses.length === 1 ? String(manageableBusinesses[0].id) : '',
        title: '',
        description: '',
        location: '',
        meeting_url: '',
        starts_at: '',
        ends_at: '',
        registration_deadline: '',
        capacity: '',
        registration_enabled: true,
    });

    useEffect(() => setEvents(data.events), [data.events]);

    function resetDraft() {
        setEditingEvent(null);
        setDraft({
            business_id: manageableBusinesses.length === 1 ? String(manageableBusinesses[0].id) : '',
            title: '',
            description: '',
            location: '',
            meeting_url: '',
            starts_at: '',
            ends_at: '',
            registration_deadline: '',
            capacity: '',
            registration_enabled: true,
        });
    }

    function startEditing(event) {
        setEditingEvent(event);
        setDraft({
            business_id: String(event.business_id),
            title: event.title,
            description: event.description ?? '',
            location: event.location ?? '',
            meeting_url: event.meeting_url ?? '',
            starts_at: toDateTimeLocal(event.starts_at),
            ends_at: toDateTimeLocal(event.ends_at),
            registration_deadline: toDateTimeLocal(event.registration_deadline),
            capacity: event.capacity ?? '',
            registration_enabled: Boolean(event.registration_enabled),
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function submitEvent(event) {
        event.preventDefault();
        setCreating(true);
        setMessage('');
        setError('');
        const eventBeingEdited = editingEvent;
        try {
            const requestData = {
                ...draft,
                starts_at: new Date(draft.starts_at).toISOString(),
                ends_at: new Date(draft.ends_at).toISOString(),
                registration_deadline: draft.registration_deadline
                    ? new Date(draft.registration_deadline).toISOString()
                    : null,
                capacity: draft.capacity === '' ? null : Number(draft.capacity),
                registration_enabled: draft.registration_enabled,
            };
            const requestPayload = { ...requestData };
            if (eventBeingEdited) {
                delete requestPayload.business_id;
            } else {
                requestPayload.business_id = Number(draft.business_id);
            }
            const url = eventBeingEdited ? `/dashboard/events/${eventBeingEdited.id}` : '/dashboard/events';
            const response = await fetch(url, {
                method: eventBeingEdited ? 'PUT' : 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(requestPayload),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validation = Object.values(payload.errors ?? {}).flat().join(' ');
                throw new Error(validation || payload.message || 'The event could not be saved.');
            }
            setMessage(eventBeingEdited
                ? 'Event changes submitted for review. Members can RSVP again after approval.'
                : 'Event submitted for review. Members can RSVP after a Super Administrator publishes it.');
            resetDraft();
            try {
                await onRefresh();
            } catch {
                setError('The event was saved, but the event list could not be refreshed.');
            }
        } catch (requestError) {
            setError(requestError.message);
        } finally {
            setCreating(false);
        }
    }

    async function registrationRequest(event, cancel = false) {
        setBusy(event.id);
        setMessage('');
        setError('');
        try {
            const response = await fetch(cancel ? event.cancellation_url : event.registration_url, {
                method: cancel ? 'DELETE' : 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validation = Object.values(payload.errors ?? {}).flat().join(' ');
                throw new Error(validation || payload.message || 'The registration could not be updated.');
            }
            setEvents((current) => current.map((item) => {
                if (item.id !== event.id) return item;
                return cancel
                    ? { ...item, registration: { ...item.registration, status: 'cancelled' }, can_register: item.registration_enabled
                        && (!item.registration_deadline || new Date(item.registration_deadline) > new Date())
                        && (!item.capacity || (item.active_registrations ?? 1) - 1 < item.capacity),
                    active_registrations: Math.max(0, (item.active_registrations ?? 1) - 1) }
                    : { ...item, registration: { id: payload.id, status: payload.status }, cancellation_url: `/dashboard/event-registrations/${payload.id}`, can_register: false, active_registrations: (item.active_registrations ?? 0) + 1 };
            }));
            setMessage(cancel ? 'Your registration was cancelled.' : 'You are registered for this event.');
        } catch (requestError) {
            setError(requestError.message);
        } finally {
            setBusy(null);
        }
    }

    return (
        <div>
            <PageHeading eyebrow="Community" title="Events">
                {data.user.is_super_admin ? 'System-managed events across all businesses and publication states.' : 'Upcoming published events open to members of any business.'}
            </PageHeading>
            {message && <p role="status" className="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">{message}</p>}
            {error && <p role="alert" className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}
            {manageableBusinesses.length > 0 && data.module_permissions?.events?.write && (
                <Card className="mb-5" eyebrow="Business event" title={editingEvent ? 'Edit event' : 'Submit an event'}>
                    <p className="text-sm text-[var(--rr-neutral-mid)]">
                        {editingEvent
                            ? 'Saving changes returns this event to draft until a Super Administrator approves it.'
                            : 'Choose one business for this event. It will remain a draft until a Super Administrator approves it.'}
                    </p>
                    <form onSubmit={submitEvent} className="mt-4 grid gap-4 sm:grid-cols-2">
                        {!editingEvent && <SearchableBusinessSelect
                            label="Business"
                            businesses={manageableBusinesses}
                            value={draft.business_id}
                            onChange={(value) => setDraft((current) => ({ ...current, business_id: value }))}
                            required
                            inputClassName="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 text-sm"
                        />}
                        <label className="text-sm font-medium">
                            Event title
                            <input required maxLength={255} value={draft.title} onChange={(event) => setDraft((current) => ({ ...current, title: event.target.value }))} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                        </label>
                        <label className="text-sm font-medium">
                            Starts at
                            <input required type="datetime-local" value={draft.starts_at} onChange={(event) => setDraft((current) => ({ ...current, starts_at: event.target.value }))} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                        </label>
                        <label className="text-sm font-medium">
                            Ends at
                            <input required type="datetime-local" value={draft.ends_at} onChange={(event) => setDraft((current) => ({ ...current, ends_at: event.target.value }))} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                        </label>
                        <label className="text-sm font-medium">
                            Location
                            <input maxLength={255} value={draft.location} onChange={(event) => setDraft((current) => ({ ...current, location: event.target.value }))} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                        </label>
                        <label className="text-sm font-medium">
                            Meeting URL
                            <input type="url" maxLength={2048} value={draft.meeting_url} onChange={(event) => setDraft((current) => ({ ...current, meeting_url: event.target.value }))} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                        </label>
                        <label className="text-sm font-medium">
                            Registration deadline
                            <input type="datetime-local" value={draft.registration_deadline} onChange={(event) => setDraft((current) => ({ ...current, registration_deadline: event.target.value }))} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                        </label>
                        <label className="text-sm font-medium">
                            Capacity
                            <input type="number" min="1" max="100000" value={draft.capacity} onChange={(event) => setDraft((current) => ({ ...current, capacity: event.target.value }))} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                        </label>
                        <label className="text-sm font-medium sm:col-span-2">
                            Description
                            <textarea rows="3" maxLength={10000} value={draft.description} onChange={(event) => setDraft((current) => ({ ...current, description: event.target.value }))} className="mt-1 w-full rounded-lg border border-[var(--rr-border)] px-3 py-2 text-sm" />
                        </label>
                        <label className="flex items-center gap-2 text-sm font-medium sm:col-span-2">
                            <input type="checkbox" checked={draft.registration_enabled} onChange={(event) => setDraft((current) => ({ ...current, registration_enabled: event.target.checked }))} className="size-5 accent-[var(--rr-primary)]" />
                            Enable RSVP after approval
                        </label>
                        <div className="flex gap-2 sm:col-span-2">
                            <button disabled={creating} className="min-h-11 w-fit rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white disabled:opacity-50">{creating ? 'Saving…' : editingEvent ? 'Save changes for approval' : 'Submit for approval'}</button>
                            {editingEvent && <button type="button" disabled={creating} onClick={resetDraft} className="min-h-11 rounded-lg border border-[var(--rr-border)] px-4 text-sm font-semibold disabled:opacity-50">Cancel</button>}
                        </div>
                    </form>
                </Card>
            )}
            <Card>
                {events.length === 0 ? (
                    <EmptyState title="No upcoming events">Published upcoming events will appear here when available for your business.</EmptyState>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {events.map((event) => (
                            <article key={event.id} className="rounded-xl border border-[var(--rr-border)] bg-[var(--rr-background)] p-5">
                                <p className="text-xs font-bold uppercase tracking-wide text-[var(--rr-accent)]">{new Date(event.starts_at).toLocaleString()}</p>
                                <h2 className="mt-2 text-lg font-semibold">{event.title}</h2>
                                {event.business_name && <p className="mt-1 text-xs font-medium text-[var(--rr-primary)]">{event.business_name}</p>}
                                {event.status === 'draft' && <p className="mt-2 text-xs font-semibold text-amber-800">Awaiting Super Administrator approval</p>}
                                <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">{event.location || 'Location not specified'}</p>
                                {event.description && <p className="mt-3 text-sm leading-6 text-[var(--rr-neutral-mid)]">{event.description}</p>}
                                <p className="mt-4 text-xs text-[var(--rr-neutral-mid)]">Ends {new Date(event.ends_at).toLocaleString()}</p>
                                {event.capacity && <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{event.active_registrations} of {event.capacity} places registered</p>}
                                {event.registration_deadline && <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Registration closes {new Date(event.registration_deadline).toLocaleString()}</p>}
                                {event.status === 'draft' ? (
                                    <p className="mt-4 text-xs font-medium text-[var(--rr-neutral-mid)]">Members can RSVP after this event is approved.</p>
                                ) : event.registration?.status === 'registered' || event.registration?.status === 'confirmed' ? (
                                    <div className="mt-4 flex items-center gap-3">
                                        <span className="text-sm font-semibold text-[var(--rr-accent)]">You are registered</span>
                                        <button type="button" disabled={busy === event.id} onClick={() => registrationRequest(event, true)} className="min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)] disabled:opacity-50">Cancel registration</button>
                                    </div>
                                ) : event.can_register ? (
                                    <button type="button" disabled={busy === event.id} onClick={() => registrationRequest(event)} className="mt-4 min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)] disabled:opacity-50">{busy === event.id ? 'Saving…' : 'RSVP'}</button>
                                ) : (
                                    <p className="mt-4 text-xs font-medium text-[var(--rr-neutral-mid)]">{event.registration_enabled ? 'Registration is closed or full.' : 'Registration is not available for this event.'}</p>
                                )}
                                {event.can_edit && <button type="button" onClick={() => startEditing(event)} className="mt-4 min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)]">Edit event</button>}
                            </article>
                        ))}
                    </div>
                )}
            </Card>
            <Card className="mt-5" eyebrow="Your participation" title="Registration and attendance history">
                {data.participation?.length ? (
                    <ul className="divide-y divide-[var(--rr-border)]">
                        {data.participation.map((item) => (
                            <li key={item.id} className="grid gap-2 py-4 first:pt-0 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                <div>
                                    <h2 className="font-semibold">{item.event || 'Event no longer available'}</h2>
                                    <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">{item.business || 'Business unavailable'}{item.starts_at ? ` · ${new Date(item.starts_at).toLocaleString()}` : ''}</p>
                                </div>
                                <div className="text-sm sm:text-right">
                                    <p className="font-semibold capitalize">{item.status === 'attended' && item.attended_at ? 'Attendance recorded' : item.status === 'attended' ? 'Attended status needs review' : item.status}</p>
                                    {item.attended_at && <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Recorded {new Date(item.attended_at).toLocaleString()}</p>}
                                    {!item.attended_at && item.status !== 'attended' && <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Registration is not proof of attendance.</p>}
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <EmptyState title="No event participation yet">Event registrations and any explicitly recorded attendance will appear here.</EmptyState>
                )}
            </Card>
            <Card className="mt-5" eyebrow="Confirmed records only" title="Recorded attendance">
                {data.attendance_history?.length ? (
                    <ul className="divide-y divide-[var(--rr-border)]">
                        {data.attendance_history.map((item) => (
                            <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0">
                                <span className="text-sm font-medium">{item.event || 'Event no longer available'}</span>
                                <time className="text-xs text-[var(--rr-neutral-mid)]" dateTime={item.recorded_at}>Marked attended {new Date(item.recorded_at).toLocaleString()}</time>
                            </li>
                        ))}
                    </ul>
                ) : <EmptyState title="No attendance has been recorded">An RSVP or registration is not proof of attendance.</EmptyState>}
            </Card>
        </div>
    );
}
