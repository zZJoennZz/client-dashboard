import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Card, EmptyState, PageHeading } from '../components/UI';
import SearchableBusinessSelect from '../../components/SearchableBusinessSelect';

function toDateTimeLocal(value) {
    if (!value) return '';
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

function emptyDraft(businesses) {
    return {
        business_id: businesses.length === 1 ? String(businesses[0].id) : '',
        title: '',
        description: '',
        location: '',
        meeting_url: '',
        starts_at: '',
        ends_at: '',
        registration_deadline: '',
        capacity: '',
        registration_enabled: true,
    };
}

function dateKey(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function calendarCells(month) {
    const year = month.getFullYear();
    const monthIndex = month.getMonth();
    const offset = new Date(year, monthIndex, 1).getDay();
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
    const cells = Array.from({ length: offset }, () => null);

    for (let day = 1; day <= daysInMonth; day += 1) {
        cells.push(new Date(year, monthIndex, day));
    }

    while (cells.length % 7 !== 0) cells.push(null);
    return cells;
}

function eventDateTime(value, options = {}) {
    if (!value) return 'Date to be announced';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? 'Date to be announced' : date.toLocaleString(undefined, options);
}

export default function Events({ data, onRefresh }) {
    const manageableBusinesses = data.event_business_options ?? [];
    const participation = data.participation ?? [];
    const attendanceHistory = data.attendance_history ?? [];
    const [events, setEvents] = useState(data.events ?? []);
    const [busy, setBusy] = useState(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [savingEvent, setSavingEvent] = useState(false);
    const [submissionOpen, setSubmissionOpen] = useState(false);
    const [pastEventsOpen, setPastEventsOpen] = useState(false);
    const [editingEvent, setEditingEvent] = useState(null);
    const [month, setMonth] = useState(() => {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), 1);
    });
    const [draft, setDraft] = useState(() => emptyDraft(manageableBusinesses));
    const submissionTitleRef = useRef(null);
    const pastEventsTitleRef = useRef(null);
    const createTriggerRef = useRef(null);
    const pastEventsTriggerRef = useRef(null);
    const lastDialogRef = useRef(null);

    useEffect(() => setEvents(data.events ?? []), [data.events]);

    useEffect(() => {
        if (submissionOpen) {
            lastDialogRef.current = 'submission';
            submissionTitleRef.current?.focus();
        } else if (pastEventsOpen) {
            lastDialogRef.current = 'past-events';
            pastEventsTitleRef.current?.focus();
        } else if (lastDialogRef.current) {
            const trigger = lastDialogRef.current === 'submission'
                ? createTriggerRef.current
                : pastEventsTriggerRef.current;
            lastDialogRef.current = null;
            trigger?.focus();
        }
    }, [submissionOpen, pastEventsOpen]);

    useEffect(() => {
        if (!submissionOpen && !pastEventsOpen) return undefined;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const handleEscape = (event) => {
            if (event.key !== 'Escape') return;
            if (pastEventsOpen) setPastEventsOpen(false);
            else if (!savingEvent) closeSubmission();
        };
        document.addEventListener('keydown', handleEscape);

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', handleEscape);
        };
    }, [submissionOpen, pastEventsOpen, savingEvent]);

    const monthLabel = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(month);
    const monthEvents = useMemo(
        () => events
            .filter((event) => {
                const date = new Date(event.starts_at);
                return !Number.isNaN(date.getTime())
                    && date.getFullYear() === month.getFullYear()
                    && date.getMonth() === month.getMonth();
            })
            .sort((first, second) => new Date(first.starts_at) - new Date(second.starts_at)),
        [events, month],
    );
    const eventsByDay = useMemo(() => {
        const counts = new Map();
        monthEvents.forEach((event) => {
            const key = dateKey(event.starts_at);
            if (key) counts.set(key, (counts.get(key) ?? 0) + 1);
        });
        return counts;
    }, [monthEvents]);
    const cells = calendarCells(month);
    const weeks = Array.from({ length: cells.length / 7 }, (_, index) => (
        cells.slice(index * 7, index * 7 + 7)
    ));
    const upcomingParticipation = useMemo(
        () => participation
            .filter((item) => ['registered', 'confirmed'].includes(item.status)
                && item.starts_at
                && new Date(item.starts_at) >= new Date())
            .sort((first, second) => new Date(first.starts_at) - new Date(second.starts_at)),
        [participation],
    );
    const pastParticipation = useMemo(
        () => participation
            .filter((item) => item.status !== 'cancelled'
                && ((item.starts_at && new Date(item.starts_at) < new Date()) || item.status === 'attended'))
            .sort((first, second) => new Date(second.starts_at || second.attended_at || 0) - new Date(first.starts_at || first.attended_at || 0)),
        [participation],
    );
    const weekdays = Array.from({ length: 7 }, (_, index) => (
        new Intl.DateTimeFormat(undefined, { weekday: 'short' }).format(new Date(2024, 0, index + 7))
    ));

    function resetDraft() {
        setEditingEvent(null);
        setDraft(emptyDraft(manageableBusinesses));
    }

    function closeSubmission() {
        if (savingEvent) return;
        setSubmissionOpen(false);
        setError('');
        resetDraft();
    }

    function openSubmission() {
        resetDraft();
        setMessage('');
        setError('');
        setSubmissionOpen(true);
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
        setMessage('');
        setError('');
        setSubmissionOpen(true);
    }

    async function submitEvent(event) {
        event.preventDefault();
        setSavingEvent(true);
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
            setSubmissionOpen(false);
            try {
                await onRefresh();
            } catch {
                setError('The event was saved, but the event list could not be refreshed.');
            }
        } catch (requestError) {
            setError(requestError.message);
        } finally {
            setSavingEvent(false);
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
                    ? {
                        ...item,
                        registration: { ...item.registration, status: 'cancelled' },
                        can_register: item.registration_enabled
                            && (!item.registration_deadline || new Date(item.registration_deadline) > new Date())
                            && (!item.capacity || (item.active_registrations ?? 1) - 1 < item.capacity),
                        active_registrations: Math.max(0, (item.active_registrations ?? 1) - 1),
                    }
                    : {
                        ...item,
                        registration: { id: payload.id, status: payload.status },
                        cancellation_url: `/dashboard/event-registrations/${payload.id}`,
                        can_register: false,
                        active_registrations: (item.active_registrations ?? 0) + 1,
                    };
            }));
            setMessage(cancel ? 'Your registration was cancelled.' : 'You are registered for this event.');
            try {
                await onRefresh();
            } catch {
                setError('Your registration was updated, but event details could not be refreshed.');
            }
        } catch (requestError) {
            setError(requestError.message);
        } finally {
            setBusy(null);
        }
    }

    function changeMonth(amount) {
        setMonth((current) => new Date(current.getFullYear(), current.getMonth() + amount, 1));
    }

    function closePastEvents() {
        setPastEventsOpen(false);
    }

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <PageHeading eyebrow="Community" title="Events">
                    {data.user.is_super_admin
                        ? 'System-managed events across all businesses and publication states.'
                        : 'Find community events, RSVP, and keep track of your participation.'}
                </PageHeading>
                {manageableBusinesses.length > 0 && data.module_permissions?.events?.write && (
                    <button
                        ref={createTriggerRef}
                        type="button"
                        onClick={openSubmission}
                        className="mb-1 min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)]"
                    >
                        Submit an event
                    </button>
                )}
            </div>

            {message && <p role="status" className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">{message}</p>}
            {error && !submissionOpen && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}

            <Card eyebrow="Event calendar" title={monthLabel}>
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-[var(--rr-neutral-mid)]">
                        {monthEvents.length} {monthEvents.length === 1 ? 'event' : 'events'} this month
                    </p>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={() => changeMonth(-1)} aria-label="Previous month" className="min-h-10 min-w-10 rounded-lg border border-[var(--rr-border)] text-lg hover:bg-[var(--rr-neutral-light)]">‹</button>
                        <button type="button" onClick={() => {
                            const now = new Date();
                            setMonth(new Date(now.getFullYear(), now.getMonth(), 1));
                        }} className="min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)]">Today</button>
                        <button type="button" onClick={() => changeMonth(1)} aria-label="Next month" className="min-h-10 min-w-10 rounded-lg border border-[var(--rr-border)] text-lg hover:bg-[var(--rr-neutral-light)]">›</button>
                    </div>
                </div>
                <div role="table" aria-label={`Events calendar for ${monthLabel}`} className="overflow-hidden rounded-xl border border-[var(--rr-border)]">
                    <div role="row" className="grid grid-cols-7">
                        {weekdays.map((weekday, index) => (
                            <div key={`${weekday}-${index}`} role="columnheader" className="border-b border-r border-[var(--rr-border)] bg-[var(--rr-neutral-light)] px-1 py-2 text-center text-[10px] font-semibold text-[var(--rr-neutral-mid)] sm:py-3 sm:text-xs">
                                {weekday}
                            </div>
                        ))}
                    </div>
                    {weeks.map((week, weekIndex) => (
                        <div key={`week-${weekIndex}`} role="row" className="grid grid-cols-7">
                            {week.map((date, dayIndex) => {
                                if (!date) {
                                    return <div key={`empty-${weekIndex}-${dayIndex}`} role="cell" className="min-h-14 border-b border-r border-[var(--rr-border)] bg-[var(--rr-background)] sm:min-h-20" />;
                                }
                                const key = dateKey(date);
                                const count = eventsByDay.get(key) ?? 0;
                                const isToday = key === dateKey(new Date());

                                return (
                                    <div key={key} role="cell" className={`min-h-14 border-b border-r border-[var(--rr-border)] p-1.5 sm:min-h-20 sm:p-2 ${count ? 'bg-[var(--rr-accent-light)]/50' : 'bg-white'}`}>
                                        <time dateTime={key} className={`inline-flex size-7 items-center justify-center rounded-full text-xs ${isToday ? 'bg-[var(--rr-primary)] font-bold text-white' : 'text-[var(--rr-neutral-dark)]'}`}>
                                            {date.getDate()}
                                        </time>
                                        {count > 0 && (
                                            <span className="mt-1 block truncate text-[9px] font-semibold text-[var(--rr-accent)] sm:text-[10px]">
                                                {count} {count === 1 ? 'event' : 'events'}
                                            </span>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ))}
                </div>
            </Card>

            <section aria-labelledby="selected-month-events">
                <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--rr-accent)]">Selected month</p>
                        <h2 id="selected-month-events" className="mt-1 text-xl font-semibold">{monthLabel} events</h2>
                    </div>
                </div>
                {monthEvents.length === 0 ? (
                    <Card>
                        <EmptyState title={`No events in ${monthLabel}`}>
                            Choose another month to browse scheduled events.
                        </EmptyState>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {monthEvents.map((event) => (
                            <EventCard
                                key={event.id}
                                event={event}
                                busy={busy === event.id}
                                onRegister={() => registrationRequest(event)}
                                onCancel={() => registrationRequest(event, true)}
                                onEdit={() => startEditing(event)}
                            />
                        ))}
                    </div>
                )}
            </section>

            <Card eyebrow="Your participation" title="Your event activity">
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(16rem,0.7fr)]">
                    <div className="rounded-xl bg-[var(--rr-primary)] p-5 text-white sm:p-6">
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-white/70">Next event</p>
                        {upcomingParticipation[0] ? (
                            <>
                                <h3 className="mt-2 text-xl font-semibold">{upcomingParticipation[0].event || 'Event details unavailable'}</h3>
                                <p className="mt-1 text-sm text-white/75">{upcomingParticipation[0].business || 'Community event'}</p>
                                <p className="mt-4 text-sm font-medium">{eventDateTime(upcomingParticipation[0].starts_at, { dateStyle: 'full', timeStyle: 'short' })}</p>
                            </>
                        ) : (
                            <p className="mt-2 text-sm leading-6 text-white/75">You have no upcoming registrations. Browse the calendar to find an event to join.</p>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-xl border border-[var(--rr-border)] bg-white p-4">
                            <p className="text-xs font-medium text-[var(--rr-neutral-mid)]">Upcoming RSVPs</p>
                            <p className="mt-2 text-3xl font-bold">{upcomingParticipation.length}</p>
                        </div>
                        <div className="rounded-xl border border-[var(--rr-border)] bg-white p-4">
                            <p className="text-xs font-medium text-[var(--rr-neutral-mid)]">Past events</p>
                            <p className="mt-2 text-3xl font-bold">{pastParticipation.length}</p>
                        </div>
                        <button
                            ref={pastEventsTriggerRef}
                            type="button"
                            onClick={() => setPastEventsOpen(true)}
                            aria-haspopup="dialog"
                            aria-expanded={pastEventsOpen}
                            className="col-span-2 flex min-h-12 items-center justify-between rounded-xl border border-[var(--rr-border)] bg-white px-4 text-left text-sm font-semibold hover:bg-[var(--rr-neutral-light)]"
                        >
                            <span>Past events and attendance</span>
                            <span className="rounded-full bg-[var(--rr-neutral-light)] px-2.5 py-1 text-xs">{pastParticipation.length}</span>
                        </button>
                    </div>
                </div>
            </Card>

            {submissionOpen && createPortal(
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="event-submission-title"
                    className="dashboard-modal-backdrop fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/55 p-0 sm:items-center sm:p-5"
                    onClick={() => { if (!savingEvent) closeSubmission(); }}
                >
                    <section
                        className="dashboard-modal-panel max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-t-2xl bg-white p-5 shadow-2xl sm:rounded-2xl sm:p-7"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--rr-accent)]">Business event</p>
                                <h2 id="event-submission-title" ref={submissionTitleRef} tabIndex={-1} className="mt-1 text-xl font-semibold">
                                    {editingEvent ? 'Edit event' : 'Submit an event'}
                                </h2>
                                <p className="mt-2 max-w-2xl text-sm text-[var(--rr-neutral-mid)]">
                                    {editingEvent
                                        ? 'Saving changes returns this event to draft until a Super Administrator approves it.'
                                        : 'Choose one business for this event. It will remain a draft until a Super Administrator approves it.'}
                                </p>
                            </div>
                            <button type="button" aria-label="Close event form" disabled={savingEvent} onClick={closeSubmission} className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-[var(--rr-border)] text-lg disabled:opacity-50">×</button>
                        </div>
                        {error && <p role="alert" className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}
                        <form onSubmit={submitEvent} className="mt-5 grid gap-4 sm:grid-cols-2">
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
                            <div className="flex justify-end gap-2 sm:col-span-2">
                                <button type="button" disabled={savingEvent} onClick={closeSubmission} className="min-h-11 rounded-lg border border-[var(--rr-border)] px-4 text-sm font-semibold disabled:opacity-50">Cancel</button>
                                <button disabled={savingEvent} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white disabled:opacity-50">
                                    {savingEvent ? 'Saving…' : editingEvent ? 'Save changes for approval' : 'Submit for approval'}
                                </button>
                            </div>
                        </form>
                    </section>
                </div>,
                document.body,
            )}

            {pastEventsOpen && createPortal(
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="past-events-title"
                    className="dashboard-modal-backdrop fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/55 p-0 sm:items-center sm:p-5"
                    onClick={closePastEvents}
                >
                    <section className="dashboard-modal-panel max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-t-2xl bg-[var(--rr-background)] p-4 shadow-2xl sm:rounded-2xl sm:p-7" onClick={(event) => event.stopPropagation()}>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--rr-accent)]">Your participation</p>
                                <h2 id="past-events-title" ref={pastEventsTitleRef} tabIndex={-1} className="mt-1 text-xl font-semibold">Past events and attendance</h2>
                                <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">Registration and explicitly recorded attendance are shown separately.</p>
                            </div>
                            <button type="button" aria-label="Close past events" onClick={closePastEvents} className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-[var(--rr-border)] text-lg hover:bg-white">×</button>
                        </div>
                        <div className="mt-5">
                            <h3 className="mb-2 text-sm font-semibold">Events you joined</h3>
                            {pastParticipation.length === 0 ? (
                                <p className="rounded-lg bg-white p-4 text-sm text-[var(--rr-neutral-mid)]">You have no past event registrations.</p>
                            ) : (
                                <ul className="divide-y divide-[var(--rr-border)] overflow-hidden rounded-xl border border-[var(--rr-border)] bg-white px-4">
                                    {pastParticipation.map((item) => (
                                        <li key={item.id} className="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p className="font-semibold">{item.event || 'Event no longer available'}</p>
                                                <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{item.business || 'Business unavailable'}{item.starts_at ? ` · ${eventDateTime(item.starts_at)}` : ''}</p>
                                            </div>
                                            <div className="sm:text-right">
                                                <p className="text-sm font-semibold capitalize">{item.status === 'attended' && item.attended_at ? 'Attendance recorded' : item.status === 'attended' ? 'Attended status needs review' : item.status}</p>
                                                {item.attended_at && <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Recorded {eventDateTime(item.attended_at)}</p>}
                                                {!item.attended_at && item.status !== 'attended' && <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Registration is not proof of attendance.</p>}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        <div className="mt-6">
                            <h3 className="mb-2 text-sm font-semibold">Attendance activity</h3>
                            <p className="mb-2 text-xs text-[var(--rr-neutral-mid)]">These are records of when attendance was marked; an RSVP alone does not count as attendance.</p>
                            {attendanceHistory.length === 0 ? (
                                <p className="rounded-lg bg-white p-4 text-sm text-[var(--rr-neutral-mid)]">No attendance has been recorded.</p>
                            ) : (
                                <ul className="divide-y divide-[var(--rr-border)] overflow-hidden rounded-xl border border-[var(--rr-border)] bg-white px-4">
                                    {attendanceHistory.map((item) => (
                                        <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-3">
                                            <span className="text-sm font-medium">{item.event || 'Event no longer available'}</span>
                                            <time className="text-xs text-[var(--rr-neutral-mid)]" dateTime={item.recorded_at}>Marked attended {eventDateTime(item.recorded_at)}</time>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>,
                document.body,
            )}
        </div>
    );
}

function EventCard({ event, busy, onRegister, onCancel, onEdit }) {
    const registered = ['registered', 'confirmed'].includes(event.registration?.status);

    return (
        <article className="flex h-full flex-col rounded-xl border border-[var(--rr-border)] bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-bold uppercase tracking-wide text-[var(--rr-accent)]">
                        {eventDateTime(event.starts_at, { dateStyle: 'medium', timeStyle: 'short' })}
                    </p>
                    {event.business_name && <p className="mt-1 text-xs font-medium text-[var(--rr-primary)]">{event.business_name}</p>}
                </div>
                {event.status === 'draft' && <span className="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-800">Awaiting approval</span>}
            </div>
            <h3 className="mt-3 text-lg font-semibold">{event.title}</h3>
            <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">{event.location || 'Location to be announced'}</p>
            {event.description && <p className="mt-3 line-clamp-3 text-sm leading-6 text-[var(--rr-neutral-mid)]">{event.description}</p>}
            <div className="mt-3 space-y-1 text-xs text-[var(--rr-neutral-mid)]">
                {event.ends_at && <p>Ends {eventDateTime(event.ends_at)}</p>}
                {event.capacity && <p>{event.active_registrations} of {event.capacity} places registered</p>}
                {event.registration_deadline && <p>Registration closes {eventDateTime(event.registration_deadline)}</p>}
            </div>
            <div className="mt-auto flex flex-wrap items-center gap-2 pt-4">
                {event.status === 'draft' ? (
                    <p className="text-xs font-medium text-[var(--rr-neutral-mid)]">Members can RSVP after approval.</p>
                ) : registered ? (
                    <>
                        <span className="text-sm font-semibold text-[var(--rr-success)]">You are registered</span>
                        <button type="button" disabled={busy} onClick={onCancel} className="min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)] disabled:opacity-50">
                            Cancel registration
                        </button>
                    </>
                ) : event.can_register ? (
                    <button type="button" disabled={busy} onClick={onRegister} className="min-h-10 rounded-lg bg-[var(--rr-primary)] px-4 text-xs font-semibold text-white hover:bg-[var(--rr-primary-light)] disabled:opacity-50">
                        {busy ? 'Saving…' : 'RSVP'}
                    </button>
                ) : (
                    <p className="text-xs font-medium text-[var(--rr-neutral-mid)]">{event.registration_enabled ? 'Registration is closed or full.' : 'Registration is not available.'}</p>
                )}
                {event.can_edit && <button type="button" onClick={onEdit} className="min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)]">Edit event</button>}
            </div>
        </article>
    );
}
