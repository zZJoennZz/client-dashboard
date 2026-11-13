import React, { useMemo, useState } from 'react';
import SearchableBusinessSelect from '../components/SearchableBusinessSelect';

export default function Engagement({ url, requestJson }) {
    const [filters, setFilters] = useState({
        search: '',
        business_id: '',
        event_id: '',
        activity_type: '',
        registration_status: '',
        from: '',
        to: '',
        summary_search: '',
        summary_sort: 'engagement',
        summary_direction: 'desc',
        activity_page: 1,
        summary_page: 1,
        event_page: 1,
        participation_page: 1,
    });
    const [searchInput, setSearchInput] = useState('');
    const params = useMemo(
        () => new URLSearchParams(
            Object.entries(filters).filter(([, value]) => value !== ''),
        ),
        [filters],
    );
    const [state, setState] = useState({
        loading: true,
        error: '',
        data: null,
    });

    const load = async (query = params) => {
        setState((current) => ({ ...current, loading: true, error: '' }));

        try {
            setState({
                loading: false,
                error: '',
                data: await requestJson(`${url}?${query}`),
            });
        } catch (error) {
            setState((current) => ({
                ...current,
                loading: false,
                error: error.message || 'Unable to load engagement data.',
            }));
        }
    };

    React.useEffect(() => {
        load();
    }, [url, params]);

    function applyFilters(event) {
        event.preventDefault();

        setFilters((current) => ({
            ...current,
            search: searchInput.trim(),
            activity_page: 1,
            event_page: 1,
            participation_page: 1,
        }));
    }

    function changeFilter(key, value, pages = []) {
        setFilters((current) => ({
            ...current,
            [key]: value,
            ...Object.fromEntries(pages.map((page) => [page, 1])),
        }));
    }

    function changePage(key, page) {
        setFilters((current) => ({ ...current, [key]: page }));
    }

    if (state.loading && !state.data) {
        return (
            <p className="rounded-xl bg-white p-6 text-sm">
                Loading member engagement…
            </p>
        );
    }

    if (state.error && !state.data) {
        return (
            <div
                role="alert"
                className="rounded-xl border border-red-200 bg-white p-6"
            >
                <p className="font-semibold text-red-700">
                    Engagement data is unavailable
                </p>
                <p className="mt-2 text-sm">{state.error}</p>
                <button
                    type="button"
                    onClick={() => load()}
                    className="mt-4 min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white"
                >
                    Retry
                </button>
            </div>
        );
    }

    const data = state.data;
    const metrics = [
        [
            'Active members',
            data.metrics.members_active_30_days,
            'Activity in the last 30 days',
        ],
        [
            'Event RSVPs',
            data.metrics.rsvps_30_days,
            'Recorded in the last 30 days',
        ],
        [
            'Active registrations',
            data.metrics.active_registrations,
            'Registered or confirmed',
        ],
        [
            'Recorded attendance',
            data.metrics.recorded_attendance,
            'Explicitly marked attended',
        ],
    ];

    return (
        <div className="space-y-6">
            <header>
                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">
                    Member engagement
                </p>
                <h2 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                    Portal activity and event participation
                </h2>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-[var(--rr-neutral-mid)]">
                    Portal activity and event registrations are reported
                    separately from attendance. Attendance appears only when
                    explicitly recorded.
                </p>
            </header>

            {state.error && (
                <p
                    role="alert"
                    className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"
                >
                    {state.error}
                </p>
            )}

            <section
                aria-label="Member engagement metrics"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                {metrics.map(([label, value, note]) => (
                    <article
                        key={label}
                        className="rounded-xl border border-[var(--rr-border)] bg-white p-5 shadow-sm"
                    >
                        <p className="text-xs font-semibold text-[var(--rr-neutral-mid)]">
                            {label}
                        </p>
                        <p className="mt-2 text-3xl font-semibold">{value}</p>
                        <p className="mt-2 text-xs text-[var(--rr-neutral-mid)]">
                            {note}
                        </p>
                    </article>
                ))}
            </section>

            <form
                onSubmit={applyFilters}
                className="grid gap-3 rounded-xl border border-[var(--rr-border)] bg-white p-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <label className="text-xs font-semibold">
                    Search member, business, or event
                    <input
                        className={fieldClass}
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                    />
                </label>

                <SearchableBusinessSelect
                    label="Business filter"
                    businesses={data.businesses}
                    value={filters.business_id}
                    onChange={(value) =>
                        changeFilter(
                            'business_id',
                            value,
                            ['activity_page', 'event_page', 'participation_page', 'summary_page'],
                        )
                    }
                    emptyLabel="All businesses"
                    inputClassName={fieldClass}
                />

                <label className="text-xs font-semibold">
                    Event
                    <select
                        className={fieldClass}
                        value={filters.event_id}
                        onChange={(event) =>
                            changeFilter('event_id', event.target.value, [
                                'event_page',
                                'participation_page',
                            ])
                        }
                    >
                        <option value="">All events</option>
                        {data.events.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.title}
                                {item.business ? ` · ${item.business}` : ''}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="text-xs font-semibold">
                    Activity type
                    <select
                        className={fieldClass}
                        value={filters.activity_type}
                        onChange={(event) =>
                            changeFilter('activity_type', event.target.value, [
                                'activity_page',
                            ])
                        }
                    >
                        <option value="">All activity</option>
                        {[
                            ['login', 'Sign-in'],
                            ['report_viewed', 'Report viewed'],
                            ['report_download', 'Report download'],
                            ['resource_viewed', 'Resource viewed'],
                            ['resource_download', 'Resource download'],
                            ['event_rsvp', 'Event RSVP'],
                            ['event_cancelled', 'Event cancellation'],
                            ['event_attended', 'Attendance recorded'],
                        ].map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="text-xs font-semibold">
                    Registration status
                    <select
                        className={fieldClass}
                        value={filters.registration_status}
                        onChange={(event) =>
                            changeFilter(
                                'registration_status',
                                event.target.value,
                                ['event_page', 'participation_page'],
                            )
                        }
                    >
                        <option value="">All statuses</option>
                        {[
                            'registered',
                            'confirmed',
                            'cancelled',
                            'attended',
                        ].map((status) => (
                            <option key={status} value={status}>
                                {status}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="text-xs font-semibold">
                    From date
                    <input
                        className={fieldClass}
                        type="date"
                        value={filters.from}
                        onChange={(event) =>
                            changeFilter('from', event.target.value, [
                                'activity_page',
                                'event_page',
                                'participation_page',
                            ])
                        }
                    />
                </label>

                <label className="text-xs font-semibold">
                    To date
                    <input
                        className={fieldClass}
                        type="date"
                        value={filters.to}
                        onChange={(event) =>
                            changeFilter('to', event.target.value, [
                                'activity_page',
                                'event_page',
                                'participation_page',
                            ])
                        }
                    />
                </label>

                <button className="min-h-11 self-end rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)]">
                    Apply filters
                </button>
            </form>

            <Section
                title="Business engagement"
                description="Recent sign-ins, report views, event registrations, and explicitly recorded attendance by business. Activity counts cover the last 30 days; last sign-in uses the latest available record."
            >
                <div className="mb-4 grid gap-3 sm:grid-cols-3">
                    <label className="text-xs font-semibold">
                        Search businesses
                        <input
                            className={fieldClass}
                            value={filters.summary_search}
                            onChange={(event) =>
                                changeFilter(
                                    'summary_search',
                                    event.target.value,
                                    ['summary_page'],
                                )
                            }
                        />
                    </label>

                    <label className="text-xs font-semibold">
                        Sort summaries
                        <select
                            className={fieldClass}
                            value={filters.summary_sort}
                            onChange={(event) =>
                                changeFilter(
                                    'summary_sort',
                                    event.target.value,
                                    ['summary_page'],
                                )
                            }
                        >
                            <option value="engagement">Engagement count</option>
                            <option value="last_login">Last sign-in</option>
                            <option value="name">Business name</option>
                        </select>
                    </label>

                    <label className="text-xs font-semibold">
                        Sort direction
                        <select
                            className={fieldClass}
                            value={filters.summary_direction}
                            onChange={(event) =>
                                changeFilter(
                                    'summary_direction',
                                    event.target.value,
                                    ['summary_page'],
                                )
                            }
                        >
                            <option value="desc">Descending</option>
                            <option value="asc">Ascending</option>
                        </select>
                    </label>
                </div>

                {data.business_summaries.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[54rem] text-left text-sm">
                            <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]">
                                <tr>
                                    {[
                                        'Business',
                                        'Last sign-in',
                                        'Sign-ins · 30d',
                                        'Report views · 30d',
                                        'Registrations · 30d',
                                        'Attendance · 30d',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="pb-3 pr-4 font-semibold"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--rr-border)]">
                                {data.business_summaries.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 pr-4 font-medium">
                                            {item.business}
                                        </td>
                                        <td className="whitespace-nowrap py-3 pr-4">
                                            {dateTime(item.last_login_at)}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.logins_30_days}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.reports_viewed_30_days}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.events_registered_30_days}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.attendance_recorded_30_days}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty>No business engagement matches this search.</Empty>
                )}

                <Pager
                    meta={data.business_summaries.meta}
                    onPage={(page) => changePage('summary_page', page)}
                />
            </Section>

            <Section
                title="Event totals"
                description="The date range filters by event start date. Registration counts include the event's matching registration records; attendance requires an explicit attendance timestamp, and no-shows are not inferred."
            >
                {data.event_totals.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[40rem] text-left text-sm">
                            <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]">
                                <tr>
                                    {[
                                        'Event',
                                        'Business',
                                        'Event date',
                                        'Registrations',
                                        'Attendance recorded',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="pb-3 pr-4 font-semibold"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--rr-border)]">
                                {data.event_totals.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 pr-4 font-medium">
                                            {item.event}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.business || '—'}
                                        </td>
                                        <td className="whitespace-nowrap py-3 pr-4">
                                            {dateTime(item.starts_at)}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.registrations}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.attendance_recorded}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty>No event totals match these filters.</Empty>
                )}

                <Pager
                    meta={data.event_totals.meta}
                    onPage={(page) => changePage('event_page', page)}
                />
            </Section>

            <Section
                title="Recent member activity"
                description="Date filters use the activity-recorded date. Entries include the associated business and time; passwords, credentials, and session data are not recorded."
            >
                {data.activities.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[42rem] text-left text-sm">
                            <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]">
                                <tr>
                                    {['Activity', 'Member', 'Business', 'When'].map(
                                        (heading) => (
                                            <th
                                                key={heading}
                                                className="pb-3 pr-4 font-semibold"
                                            >
                                                {heading}
                                            </th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--rr-border)]">
                                {data.activities.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 pr-4">
                                            {activityName(item.type)}
                                            {item.subject
                                                ? ` · ${item.subject}`
                                                : ''}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.member}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.business || '—'}
                                        </td>
                                        <td className="whitespace-nowrap py-3 pr-4">
                                            {dateTime(item.occurred_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty>No activity matches these filters.</Empty>
                )}

                <Pager
                    meta={data.activities.meta}
                    onPage={(page) => changePage('activity_page', page)}
                />
            </Section>

            <Section
                title="Event participation history"
                description="Date filters use the registration date. Registration and attendance are separate facts; an RSVP is not proof of attendance."
            >
                {data.participation.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[42rem] text-left text-sm">
                            <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]">
                                <tr>
                                    {[
                                        'Member',
                                        'Business',
                                        'Event',
                                        'Registration',
                                        'Registered',
                                        'Attendance',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="pb-3 pr-4 font-semibold"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--rr-border)]">
                                {data.participation.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 pr-4">
                                            {item.member}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.business || '—'}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {item.event || 'Event unavailable'}
                                        </td>
                                        <td className="py-3 pr-4 capitalize">
                                            {item.status}
                                        </td>
                                        <td className="whitespace-nowrap py-3 pr-4">
                                            {dateTime(item.registered_at)}
                                        </td>
                                        <td className="whitespace-nowrap py-3 pr-4">
                                            {item.attended_at
                                                ? dateTime(item.attended_at)
                                                : 'Not recorded'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty>No event participation matches these filters.</Empty>
                )}

                <Pager
                    meta={data.participation.meta}
                    onPage={(page) => changePage('participation_page', page)}
                />
            </Section>
        </div>
    );
}

function Section({ title, description, children }) {
    return (
        <section className="rounded-xl border border-[var(--rr-border)] bg-white p-5 shadow-sm">
            <h3 className="font-semibold">{title}</h3>
            <p className="mb-4 mt-1 text-xs leading-5 text-[var(--rr-neutral-mid)]">
                {description}
            </p>
            {children}
        </section>
    );
}

function Empty({ children }) {
    return (
        <p className="rounded-lg border border-dashed border-[var(--rr-border)] bg-[var(--rr-background)] p-8 text-center text-sm text-[var(--rr-neutral-mid)]">
            {children}
        </p>
    );
}

function Pager({ meta, onPage }) {
    if (meta.last_page <= 1) return null;

    return (
        <div className="mt-4 flex items-center justify-between border-t border-[var(--rr-border)] pt-4">
            <span className="text-xs text-[var(--rr-neutral-mid)]">
                Page {meta.current_page} of {meta.last_page} · {meta.total} records
            </span>
            <div className="flex gap-2">
                <button
                    type="button"
                    disabled={meta.current_page <= 1}
                    onClick={() => onPage(meta.current_page - 1)}
                    className={pagerClass}
                >
                    Previous
                </button>
                <button
                    type="button"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => onPage(meta.current_page + 1)}
                    className={pagerClass}
                >
                    Next
                </button>
            </div>
        </div>
    );
}

function activityName(type) {
    return (
        {
            login: 'Signed in',
            report_viewed: 'Viewed report',
            report_download: 'Downloaded report',
            report_downloaded: 'Downloaded report',
            resource_viewed: 'Viewed resource',
            resource_download: 'Downloaded resource',
            resource_downloaded: 'Downloaded resource',
            event_rsvp: 'Registered for event',
            event_registered: 'Registered for event',
            event_cancelled: 'Cancelled event registration',
            event_attended: 'Attendance recorded',
        }[type] ?? 'Portal activity'
    );
}

function dateTime(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

const fieldClass =
    'mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 py-2 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20';

const pagerClass =
    'min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold disabled:opacity-40';