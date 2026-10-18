import React from 'react';
import { Card, DashboardLink, Definition, EmptyState, PageHeading, Status } from '../components/UI';

export default function Overview({ data, navigate }) {
    const business = data.business;
    const membership = data.membership;

    return (
        <div className="space-y-6">
            <section className="overflow-hidden rounded-2xl bg-[var(--rr-primary)] px-6 py-7 text-white shadow-sm sm:px-8 sm:py-9">
                <p className="text-[10px] font-bold uppercase tracking-[0.17em] text-[var(--rr-accent-light)]">{data.user.is_super_admin ? 'Service provider workspace' : 'Member workspace'}</p>
                <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Welcome back, {data.user.name}.</h1>
                <p className="mt-3 max-w-2xl text-sm leading-6 text-white/75">{data.user.is_super_admin ? 'Platform-wide visibility across all member businesses, accounts, and activity.' : 'Your home base for your business and Rediscover Roanoke membership.'}</p>
            </section>

            {data.user.is_super_admin && (
                <Card eyebrow="Organization-wide access" title="All businesses">
                    <p className="mb-5 text-sm text-[var(--rr-neutral-mid)]">{data.businesses.length} businesses are available across the platform.</p>
                    {data.businesses.length === 0 ? (
                        <EmptyState title="No businesses have been created">Business records will appear here when they are added.</EmptyState>
                    ) : (
                        <ul className="divide-y divide-[var(--rr-border)]">
                            {data.businesses.map((item) => (
                                <li key={item.id} className="grid gap-2 py-4 first:pt-0 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                    <div>
                                        <h3 className="font-semibold">{item.name}</h3>
                                        <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">{[item.category, item.city, item.state].filter(Boolean).join(' · ') || 'Business details not provided'}</p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Status tone={item.membership?.status === 'active' ? 'success' : 'neutral'}>{item.membership?.status ?? 'No membership'}</Status>
                                        <Status tone={item.directory_status === 'Published' ? 'success' : 'neutral'}>{item.directory_status}</Status>
                                        <Status tone={item.is_active ? 'success' : 'neutral'}>{item.is_active ? 'Active business' : 'Inactive business'}</Status>
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
                        If you believe this is an error, contact the Rediscover Roanoke team. No business information is available in this account yet.
                    </EmptyState>
                </Card>
            )}

            {business && (
                <div className="grid gap-5 xl:grid-cols-[1.4fr_0.8fr]">
                    <Card eyebrow="Your business" title={business.name}>
                        <p className="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{business.description || 'No business description has been added yet.'}</p>
                        <dl className="mt-6 grid gap-5 border-t border-[var(--rr-border)] pt-5 sm:grid-cols-2">
                            <Definition label="Category" value={business.category} />
                            <Definition label="Location" value={[business.city, business.state].filter(Boolean).join(', ')} />
                            <Definition label="Contact" value={business.contact_name} />
                            <Definition label="Directory status" value={business.directory_status} />
                        </dl>
                        <DashboardLink href="/dashboard/business" navigate={navigate} className="mt-5 inline-flex min-h-11 items-center rounded-sm text-sm font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">
                            View business profile <span className="ml-2" aria-hidden="true">→</span>
                        </DashboardLink>
                    </Card>

                    <Card eyebrow="Your plan" title={membership?.plan || 'Membership'}>
                        {membership ? (
                            <>
                                <div className="mt-3"><Status tone={membership.status === 'active' ? 'success' : 'neutral'}>{membership.status}</Status></div>
                                <p className="mt-4 text-sm text-[var(--rr-neutral-mid)]">Started {membership.start_date || 'date not recorded'}</p>
                                {membership.payment_is_simulated && (
                                    <p className="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                                        Test registration only: no payment was collected. Payment processing has not been connected.
                                    </p>
                                )}
                                <DashboardLink href="/dashboard/membership" navigate={navigate} className="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-[var(--rr-primary)] hover:underline">
                                    Membership details <span className="ml-2" aria-hidden="true">→</span>
                                </DashboardLink>
                            </>
                        ) : (
                            <p className="mt-3 text-sm text-[var(--rr-neutral-mid)]">No membership record is associated with this business.</p>
                        )}
                    </Card>
                </div>
            )}

            <section>
                <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 className="text-lg font-semibold">Your workspace</h2>
                        <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">{data.user.is_super_admin ? 'Organization-wide records across all businesses.' : 'Open a member area to see information available to your account.'}</p>
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        ['Reports', 'Published business reports', '/dashboard/reports', data.reports.length],
                        ['Events', 'Upcoming published events', '/dashboard/events', data.events.length],
                        ['Resources', 'Member resources', '/dashboard/resources', data.resources.length],
                        ['Marketing tools', 'Scheduled and completed activity', '/dashboard/marketing', data.marketing.length],
                    ].map(([title, description, href, count]) => (
                        <DashboardLink key={title} href={href} navigate={navigate} className="group rounded-xl border border-[var(--rr-border)] bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">
                            <div className="flex items-center justify-between">
                                <h3 className="font-semibold text-[var(--rr-neutral-dark)]">{title}</h3>
                                <span className="flex size-9 items-center justify-center rounded-lg bg-[var(--rr-accent-light)] text-sm font-bold text-[var(--rr-accent)]">{count}</span>
                            </div>
                            <p className="mt-3 text-xs leading-5 text-[var(--rr-neutral-mid)]">{description}</p>
                            <p className="mt-3 text-xs font-semibold text-[var(--rr-primary)] group-hover:text-[var(--rr-primary-light)]">Open area →</p>
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
                                    <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{report.business_name ? `${report.business_name} · ` : ''}{report.reporting_period || 'Reporting period not specified'}</p>
                                </li>
                            ))}
                        </ul>
                    ) : <div className="mt-4"><EmptyState title="No published reports yet">Reports will appear here when they are shared with your business.</EmptyState></div>}
                </Card>
                <Card eyebrow="Coming up" title="Upcoming events">
                    {data.events.length ? (
                        <ul className="mt-4 divide-y divide-[var(--rr-border)]">
                            {data.events.slice(0, 3).map((event) => (
                                <li key={event.id} className="py-3 first:pt-0 last:pb-0">
                                    <p className="text-sm font-medium">{event.title}</p>
                                    <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{event.business_name ? `${event.business_name} · ` : ''}{new Date(event.starts_at).toLocaleString()} · {event.location || 'Location not specified'}</p>
                                </li>
                            ))}
                        </ul>
                    ) : <div className="mt-4"><EmptyState title="No upcoming events">Published events for your business will appear here.</EmptyState></div>}
                </Card>
            </div>

            <div className="grid gap-5 xl:grid-cols-[0.8fr_1.2fr]">
                <Card eyebrow="Last 30 days" title="Your engagement">
                    <dl className="mt-4 grid grid-cols-3 gap-3">
                        <div className="rounded-lg bg-[var(--rr-background)] p-3"><dt className="text-xs text-[var(--rr-neutral-mid)]">RSVPs</dt><dd className="mt-1 text-2xl font-semibold">{data.engagement?.rsvps_30_days ?? 0}</dd></div>
                        <div className="rounded-lg bg-[var(--rr-background)] p-3"><dt className="text-xs text-[var(--rr-neutral-mid)]">Upcoming</dt><dd className="mt-1 text-2xl font-semibold">{data.engagement?.upcoming_registered ?? 0}</dd></div>
                        <div className="rounded-lg bg-[var(--rr-background)] p-3"><dt className="text-xs text-[var(--rr-neutral-mid)]">Attended</dt><dd className="mt-1 text-2xl font-semibold">{data.engagement?.recorded_attendance ?? 0}</dd></div>
                    </dl>
                    <p className="mt-3 text-xs leading-5 text-[var(--rr-neutral-mid)]">Attendance is shown only when an administrator has explicitly recorded it.</p>
                </Card>
                <Card eyebrow="Portal and event history" title="Recent activity">
                    {data.activity?.length ? (
                        <ul className="divide-y divide-[var(--rr-border)]">
                            {data.activity.slice(0, 6).map((item) => (
                                <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0">
                                    <span className="text-sm">{activityLabel(item.type)}{item.subject ? ` · ${item.subject}` : ''}</span>
                                    <time className="text-xs text-[var(--rr-neutral-mid)]" dateTime={item.created_at}>{new Date(item.created_at).toLocaleString()}</time>
                                </li>
                            ))}
                        </ul>
                    ) : <EmptyState title="No activity recorded yet">Your sign-ins, downloads, and event updates will appear here.</EmptyState>}
                </Card>
            </div>
        </div>
    );
}

function activityLabel(type) {
    return ({
        login: 'Signed in',
        resource_download: 'Downloaded a resource',
        report_download: 'Downloaded a report',
        event_rsvp: 'Registered for an event',
        event_cancelled: 'Cancelled an event registration',
        event_attended: 'Attendance recorded',
    })[type] ?? 'Portal activity';
}
