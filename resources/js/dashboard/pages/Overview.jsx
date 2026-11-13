
import React from 'react';
import {
    Card,
    DashboardLink,
    Definition,
    EmptyState,
    Status,
} from '../components/UI';

export default function Overview({ data, navigate }) {
    const business = data.business;
    const membership = data.membership;
    const engagement = data.engagement ?? {};

    const engagementMetrics = [
        { label: 'Logins', value: engagement.logins_30_days },
        { label: 'Report views', value: engagement.report_views_30_days },
        { label: 'Resource views', value: engagement.resource_views_30_days },
        { label: 'Event registrations', value: engagement.rsvps_30_days },
    ];
    const workspaceAreas = [
        { title: 'Reports', description: 'Published business reports', href: '/dashboard/reports', count: data.reports.length, icon: '▧' },
        { title: 'Events', description: 'Upcoming published events', href: '/dashboard/events', count: data.events.length, icon: '▦' },
        { title: 'Resource library', description: 'Guides and member resources', href: '/dashboard/resources', count: data.resources.length, icon: '▱' },
        { title: 'Marketing tools', description: 'Scheduled and completed promotions', href: '/dashboard/marketing', count: data.marketing_activities?.length ?? 0, icon: '✳' },
    ];

    return (
        <div className="min-w-0 space-y-6 sm:space-y-8">
            <section className="relative isolate overflow-hidden rounded-2xl bg-gradient-to-br from-neutral-950 via-[var(--rr-primary)] to-slate-800 px-5 py-7 text-white shadow-xl shadow-black/10 sm:rounded-3xl sm:px-8 sm:py-9 lg:px-10 lg:py-10">
                <div aria-hidden="true" className="pointer-events-none absolute -right-24 -top-32 size-80 rounded-full border-[36px] border-white/[0.04]" />
                <div aria-hidden="true" className="pointer-events-none absolute -bottom-36 right-1/4 size-72 rounded-full bg-[var(--rr-accent)]/10 blur-3xl" />
                <div className="relative grid min-w-0 gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,0.65fr)] lg:items-center">
                    <div className="min-w-0">
                        <p className="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.17em] text-white/65">
                            <span className="size-2 rounded-full bg-[var(--rr-accent)]" />
                            {data.user.is_super_admin ? 'Super Administrator workspace' : 'Member workspace'}
                        </p>

                        <h1 className="mt-4 max-w-3xl text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">
                            Welcome back, {data.user.name || 'member'}.
                        </h1>

                        <p className="mt-3 max-w-2xl text-sm leading-6 text-white/70 sm:text-base">
                            {data.user.is_super_admin
                                ? 'Platform-wide visibility across all member businesses, accounts, and activity.'
                                : 'Your home base for your business, reports, events, and membership.'}
                        </p>

                        {!data.user.is_super_admin && (
                            <div className="mt-6 flex flex-wrap gap-3">
                                <DashboardLink
                                    href="/dashboard/reports"
                                    navigate={navigate}
                                    className="inline-flex min-h-11 items-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-[var(--rr-primary)] transition hover:-translate-y-0.5 hover:bg-white/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                                >
                                    View reports →
                                </DashboardLink>

                                <DashboardLink
                                    href="/dashboard/resources"
                                    navigate={navigate}
                                    className="inline-flex min-h-11 items-center rounded-lg border border-white/30 bg-white/5 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                                >
                                    Resource library →
                                </DashboardLink>
                            </div>
                        )}
                    </div>
                    <aside className="rounded-2xl border border-white/10 bg-white/[0.06] p-5 backdrop-blur-sm sm:p-6">
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-white/55">
                            {data.user.is_super_admin ? 'Platform snapshot' : 'Your workspace'}
                        </p>
                        <p className="mt-3 truncate text-lg font-semibold">
                            {data.user.is_super_admin ? `${data.businesses.length} businesses` : business?.name || 'No business connected'}
                        </p>
                        <div className="mt-4 grid grid-cols-2 gap-3 border-t border-white/10 pt-4">
                            <div>
                                <p className="text-xs text-white/55">{data.user.is_super_admin ? 'Active' : 'Membership'}</p>
                                <p className="mt-1 truncate text-sm font-medium">
                                    {data.user.is_super_admin
                                        ? data.businesses.filter((item) => item.is_active).length
                                        : membership?.status || 'Not connected'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-white/55">{data.user.is_super_admin ? 'Published' : 'Plan'}</p>
                                <p className="mt-1 truncate text-sm font-medium">
                                    {data.user.is_super_admin
                                        ? data.businesses.filter((item) => item.directory_status === 'Published').length
                                        : membership?.plan || 'No plan'}
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </section>

            {!data.user.is_super_admin && (
                <section aria-label="Workspace at a glance" className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {workspaceAreas.map((area) => (
                        <div key={area.title} className="flex min-w-0 items-center gap-4 rounded-2xl border border-[var(--rr-border)] bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md sm:p-5">
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--rr-accent-light)] text-lg font-semibold text-[var(--rr-accent)]" aria-hidden="true">{area.icon}</span>
                            <div className="min-w-0">
                                <p className="text-xs text-[var(--rr-neutral-mid)]">{area.title}</p>
                                <p className="mt-1 text-2xl font-semibold tracking-tight">{area.count}</p>
                            </div>
                        </div>
                    ))}
                </section>
            )}

            {data.user.is_super_admin && (
                <Card eyebrow="Organization-wide access" title="All businesses">
                    <p className="mb-5 text-sm text-[var(--rr-neutral-mid)]">
                        {data.businesses.length} businesses are available across the platform.
                    </p>

                    {data.businesses.length === 0 ? (
                        <EmptyState title="No businesses have been created">
                            Business records will appear here when they are added.
                        </EmptyState>
                    ) : (
                        <ul className="divide-y divide-[var(--rr-border)]">
                            {data.businesses.map((item) => (
                                <li
                                    key={item.id}
                                    className="grid gap-2 py-4 first:pt-0 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                                >
                                    <div>
                                        <h3 className="font-semibold">{item.name}</h3>
                                        <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">
                                            {[item.category, item.city, item.state]
                                                .filter(Boolean)
                                                .join(' · ') || 'Business details not provided'}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2">
                                        <Status tone={item.membership?.status === 'active' ? 'success' : 'neutral'}>
                                            {item.membership?.status ?? 'No membership'}
                                        </Status>
                                        <Status tone={item.directory_status === 'Published' ? 'success' : 'neutral'}>
                                            {item.directory_status}
                                        </Status>
                                        <Status tone={item.is_active ? 'success' : 'neutral'}>
                                            {item.is_active ? 'Active business' : 'Inactive business'}
                                        </Status>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            )}

            {!business && !data.user.is_super_admin && (
                <Card>
                    <EmptyState title="No active business is connected to your account">
                        If you believe this is an error, contact The Argyle Buzz team.
                        No business information is available in this account yet.
                    </EmptyState>
                </Card>
            )}

            {business && (
                <div className="grid gap-5 xl:grid-cols-[1.4fr_0.8fr]">
                    <Card eyebrow="Your business" title={business.name}>
                        <p className="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">
                            {business.description || 'No business description has been added yet.'}
                        </p>

                        <dl className="mt-6 grid gap-5 border-t border-[var(--rr-border)] pt-5 sm:grid-cols-2">
                            <Definition label="Category" value={business.category} />
                            <Definition
                                label="Location"
                                value={[business.city, business.state].filter(Boolean).join(', ')}
                            />
                            <Definition label="Contact" value={business.contact_name} />
                            <Definition label="Directory status" value={business.directory_status} />
                        </dl>

                        <DashboardLink
                            href="/dashboard/business"
                            navigate={navigate}
                            className="mt-5 inline-flex min-h-11 items-center rounded-sm text-sm font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]"
                        >
                            View business profile <span className="ml-2" aria-hidden="true">→</span>
                        </DashboardLink>
                    </Card>

                    <Card eyebrow="Your plan" title={membership?.plan || 'Membership'}>
                        {membership ? (
                            <>
                                <div className="mt-3">
                                    <Status tone={membership.status === 'active' ? 'success' : 'neutral'}>
                                        {membership.status}
                                    </Status>
                                </div>

                                <p className="mt-4 text-sm text-[var(--rr-neutral-mid)]">
                                    Started {membership.start_date || 'date not recorded'}
                                </p>

                                {/* {membership.payment_is_simulated && (
                                    <p className="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                                        Test registration only: no payment was collected.
                                        Payment processing has not been connected.
                                    </p>
                                )} */}

                                <DashboardLink
                                    href="/dashboard/membership"
                                    navigate={navigate}
                                    className="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-[var(--rr-primary)] hover:underline"
                                >
                                    Membership details <span className="ml-2" aria-hidden="true">→</span>
                                </DashboardLink>
                            </>
                        ) : (
                            <p className="mt-3 text-sm text-[var(--rr-neutral-mid)]">
                                No membership record is associated with this business.
                            </p>
                        )}
                    </Card>
                </div>
            )}

            <section>
                <div className="mb-3">
                    <h2 className="text-lg font-semibold">Your workspace</h2>
                    <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">
                        Open a member area to see information available to your account.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {workspaceAreas.map((area) => (
                        <DashboardLink
                            key={area.title}
                            href={area.href}
                            navigate={navigate}
                            className="group min-w-0 rounded-2xl border border-[var(--rr-border)] bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-black/15 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--rr-accent-light)] text-lg font-semibold text-[var(--rr-accent)]" aria-hidden="true">
                                    {area.icon}
                                </span>
                                <span className="text-xs font-semibold text-[var(--rr-neutral-mid)] transition group-hover:text-[var(--rr-primary)]">Open area →</span>
                            </div>
                            <h3 className="mt-5 font-semibold text-[var(--rr-neutral-dark)]">{area.title}</h3>
                            <p className="mt-2 min-h-10 text-xs leading-5 text-[var(--rr-neutral-mid)]">
                                {area.description}
                            </p>
                        </DashboardLink>
                    ))}
                </div>
            </section>

            <div className="grid gap-5 xl:grid-cols-2">
                <Card eyebrow="Latest" title="Recent reports">
                    {data.reports.length ? (
                        <ul className="mt-4 divide-y divide-[var(--rr-border)]">
                            {data.reports.slice(0, 3).map((report) => (
                                <li key={report.id} className="py-3 first:pt-0 last:pb-0">
                                    <p className="text-sm font-medium">{report.title}</p>
                                    <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">
                                        {report.business_name ? `${report.business_name} · ` : ''}
                                        {report.reporting_period || 'Reporting period not specified'}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="mt-4">
                            <EmptyState title="No published reports yet">
                                Reports will appear here when they are shared with your business.
                            </EmptyState>
                        </div>
                    )}
                </Card>

                <Card eyebrow="Coming up" title="Upcoming events">
                    {data.events.length ? (
                        <ul className="mt-4 divide-y divide-[var(--rr-border)]">
                            {data.events.slice(0, 3).map((event) => (
                                <li key={event.id} className="py-3 first:pt-0 last:pb-0">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <p className="text-sm font-medium">{event.title}</p>
                                        <Status
                                            tone={['registered', 'confirmed'].includes(event.registration?.status)
                                                ? 'success'
                                                : 'neutral'}
                                        >
                                            {registrationLabel(event.registration?.status)}
                                        </Status>
                                    </div>

                                    <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">
                                        {formatDateTime(event.starts_at)}
                                        {event.ends_at ? ` – ${formatDateTime(event.ends_at)}` : ''}
                                    </p>

                                    <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">
                                        {[event.business_name, event.location].filter(Boolean).join(' · ')
                                            || 'Location not specified'}
                                    </p>

                                    {event.registration?.status === 'cancelled' && (
                                        <p className="mt-2 text-xs text-[var(--rr-neutral-mid)]">
                                            Your previous registration was cancelled.
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="mt-4">
                            <EmptyState title="No upcoming events">
                                Published events for your business will appear here.
                            </EmptyState>
                        </div>
                    )}

                    <DashboardLink
                        href="/dashboard/events"
                        navigate={navigate}
                        className="mt-4 inline-flex min-h-10 items-center text-sm font-semibold text-[var(--rr-primary)] hover:underline"
                    >
                        Browse all events →
                    </DashboardLink>
                </Card>
            </div>

            <div className="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
                <Card eyebrow="Last 30 days" title="Your engagement">
                    <dl className="mt-4 grid grid-cols-2 gap-3">
                        {engagementMetrics.map((metric) => (
                            <div
                                key={metric.label}
                                className="rounded-lg bg-[var(--rr-background)] p-4"
                            >
                                <dt className="text-xs text-[var(--rr-neutral-mid)]">
                                    {metric.label}
                                </dt>
                                <dd className="mt-1 text-2xl font-semibold">
                                    {metric.value ?? '—'}
                                </dd>
                                {metric.value == null && (
                                    <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">
                                        Not available
                                    </p>
                                )}
                            </div>
                        ))}
                    </dl>

                    <div className="mt-3 rounded-lg border border-[var(--rr-border)] p-3">
                        <p className="text-xs text-[var(--rr-neutral-mid)]">
                            Recorded attendance
                        </p>
                        <p className="mt-1 text-lg font-semibold">
                            {engagement.recorded_attendance ?? '—'}
                        </p>
                        <p className="mt-1 text-xs leading-5 text-[var(--rr-neutral-mid)]">
                            Attendance appears here only when an administrator has recorded it.
                        </p>
                    </div>
                </Card>

                <Card eyebrow="Portal and event history" title="Recent activity">
                    {data.activity?.length ? (
                        <ul className="divide-y divide-[var(--rr-border)]">
                            {data.activity.slice(0, 6).map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">
                                            {activityLabel(item.type)}
                                        </p>
                                        {item.subject && (
                                            <p className="mt-1 break-words text-xs text-[var(--rr-neutral-mid)]">
                                                {item.subject}
                                            </p>
                                        )}
                                    </div>

                                    <time
                                        className="shrink-0 text-xs text-[var(--rr-neutral-mid)]"
                                        dateTime={item.created_at}
                                        title={formatDateTime(item.created_at)}
                                    >
                                        {formatRelativeTime(item.created_at)}
                                    </time>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState title="No activity recorded yet">
                            Your sign-ins, available report/resource activity, and event updates
                            will appear here when recorded.
                        </EmptyState>
                    )}
                </Card>
            </div>
        </div>
    );
}

function registrationLabel(status) {
    const labels = {
        registered: 'Registered',
        confirmed: 'Confirmed',
        attended: 'Attended',
        cancelled: 'Cancelled',
        waitlisted: 'Waitlisted',
    };

    return labels[status] ?? 'Not registered';
}

function activityLabel(type) {
    const labels = {
        login: 'Signed in',
        report_view: 'Viewed a report',
        report_viewed: 'Viewed a report',
        report_download: 'Downloaded a report',
        report_downloaded: 'Downloaded a report',
        resource_view: 'Viewed a resource',
        resource_viewed: 'Viewed a resource',
        resource_download: 'Downloaded a resource',
        resource_downloaded: 'Downloaded a resource',
        event_rsvp: 'Registered for an event',
        event_cancelled: 'Cancelled an event registration',
        event_attended: 'Attendance recorded',
        profile_updated: 'Updated profile details',
    };

    if (labels[type]) {
        return labels[type];
    }

    return (type || 'Portal activity')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function formatDateTime(value) {
    if (!value) return 'Date not specified';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return 'Date not specified';

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function formatRelativeTime(value) {
    if (!value) return 'Time unavailable';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return 'Time unavailable';

    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto',
    });

    if (Math.abs(seconds) < 60) return formatter.format(seconds, 'second');

    const minutes = Math.round(seconds / 60);
    if (Math.abs(minutes) < 60) return formatter.format(minutes, 'minute');

    const hours = Math.round(minutes / 60);
    if (Math.abs(hours) < 24) return formatter.format(hours, 'hour');

    const days = Math.round(hours / 24);
    if (Math.abs(days) < 30) return formatter.format(days, 'day');

    return formatDateTime(value);
}
