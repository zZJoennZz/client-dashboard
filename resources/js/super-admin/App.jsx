import React, { useCallback, useEffect, useMemo, useState } from 'react';
import AdminWorkflows from './Workflows';
import Engagement from './Engagement';
import PlatformSettings from './PlatformSettings';

const navigation = [
    ['overview', 'Overview'],
    ['businesses', 'Member businesses'],
    ['accounts', 'Members and accounts'],
    ['memberships', 'Memberships'],
    ['content', 'Content and resources'],
    ['reports', 'Reports'],
    ['events', 'Events and registrations'],
    ['marketing', 'Marketing and promotions'],
    ['orders', 'Premium services and orders'],
    ['billing', 'Billing and payments'],
    ['analytics', 'Reporting and analytics'],
    ['engagement', 'Member engagement'],
    ['communications', 'Member communications'],
    ['audit', 'Audit logs'],
    ['settings', 'Platform settings'],
];
const availableSections = new Set(['overview', 'businesses', 'accounts', 'memberships', 'content', 'reports', 'events', 'marketing', 'orders', 'billing', 'analytics', 'engagement', 'communications', 'audit', 'settings']);

function sectionFromPath(pathname) {
    const segment = pathname.replace(/^\/super-admin\/?/, '').split('/')[0];
    if (!segment) return 'overview';
    return navigation.some(([key]) => key === segment) ? segment : null;
}

function Definition({ label, value }) {
    return <div className="min-w-0"><dt className="text-[10px] font-bold uppercase tracking-[0.13em] text-[var(--rr-neutral-mid)]">{label}</dt><dd className="mt-1 break-words text-sm">{value || 'Not provided'}</dd></div>;
}

async function requestJson(url, options = {}) {
    const formData = options.body instanceof FormData;
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.body ? {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            } : {}),
            ...(!formData && options.body ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
        },
    });

    if (response.status === 401 || response.redirected) {
        window.location.assign(document.getElementById('super-admin')?.dataset.loginUrl ?? '/login');
        throw new Error('Your session has expired. Please sign in again.');
    }

    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        const errors = Object.values(payload.errors ?? {}).flat();
        throw new Error(errors.join(' ') || payload.message || `The request failed (HTTP ${response.status}).`);
    }

    return response.status === 204 ? null : response.json();
}

export default function App({
    overviewUrl, engagementUrl, settingsUrl, settingsUpdateUrl, businessesUrl, accountsUrl, workspaceUrl, loginUrl,
    membershipsUrl, resourcesUrl, reportsUrl, eventsUrl, marketingUrl,
    servicesUrl, ordersUrl, announcementsUrl, exportUrl,
}) {
    const [section, setSection] = useState(() => sectionFromPath(window.location.pathname));
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        const onPopState = () => setSection(sectionFromPath(window.location.pathname));
        window.addEventListener('popstate', onPopState);
        return () => window.removeEventListener('popstate', onPopState);
    }, []);

    const navigate = useCallback((key) => {
        const path = key === 'overview' ? '/super-admin' : `/super-admin/${key}`;
        if (window.location.pathname !== path) {
            window.history.pushState({}, '', path);
        }
        setSection(key);
        setMenuOpen(false);
        window.scrollTo({ top: 0, behavior: 'instant' });
    }, []);

    const title = navigation.find(([key]) => key === section)?.[1] ?? (section ? 'Overview' : 'Page not found');

    return (
        <div className="min-h-screen bg-[var(--rr-background)] lg:flex">
            {menuOpen && <button type="button" aria-label="Close navigation menu" onClick={() => setMenuOpen(false)} className="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" />}
            <aside className={`fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-[var(--rr-primary)] text-white transition-transform lg:translate-x-0 ${menuOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="flex h-16 items-center justify-between border-b border-white/10 px-5">
                    <a href="/" className="rounded bg-white px-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-warning)]">
                        <img src="/the-argyle-buzz-logo.png" alt="The Argyle Buzz" width="475" height="88" className="h-10 w-auto max-w-[220px]" />
                    </a>
                    <button type="button" aria-label="Close navigation" onClick={() => setMenuOpen(false)} className="min-h-11 min-w-11 rounded-lg text-white/80 hover:bg-white/10 lg:hidden">×</button>
                </div>
                <p className="px-5 pb-2 pt-5 text-[10px] font-bold uppercase tracking-[0.16em] text-white/50">Service provider</p>
                <nav aria-label="Service provider navigation" className="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
                    {navigation.map(([key, label]) => {
                        const ready = availableSections.has(key);
                        return ready ? (
                            <a key={key} href={key === 'overview' ? '/super-admin' : `/super-admin/${key}`} aria-current={section === key ? 'page' : undefined} onClick={(event) => {
                                event.preventDefault();
                                navigate(key);
                            }} className={`flex min-h-11 items-center rounded-lg px-3 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-warning)] ${section === key ? 'bg-white/15 font-semibold text-white' : 'text-white/70 hover:bg-white/5 hover:text-white'}`}>
                                {label}
                            </a>
                        ) : (
                            <span key={key} aria-disabled="true" title="Not implemented yet" className="flex min-h-11 cursor-not-allowed items-center justify-between rounded-lg px-3 text-sm text-white/35">
                                {label}<span className="text-[9px] uppercase tracking-wide">Planned</span>
                            </span>
                        );
                    })}
                </nav>
                <div className="border-t border-white/10 p-4">
                    <a href="/dashboard" className="flex min-h-11 items-center rounded-lg px-3 text-sm text-white/70 hover:bg-white/5 hover:text-white">Member workspace</a>
                    <form method="POST" action="/logout" className="mt-1">
                        <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content ?? ''} />
                        <button type="submit" className="min-h-11 w-full rounded-lg px-3 text-left text-sm text-white/70 hover:bg-white/5 hover:text-white">Log out</button>
                    </form>
                </div>
            </aside>
            <div className="min-w-0 flex-1 lg:pl-72">
                <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-[var(--rr-border)] bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-10">
                    <button type="button" aria-label="Open navigation menu" onClick={() => setMenuOpen(true)} className="flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-[var(--rr-border)] text-[var(--rr-primary)] lg:hidden">☰</button>
                    <div className="min-w-0">
                        <h1 className="truncate text-sm font-semibold">{title}</h1>
                        <p className="hidden text-xs text-[var(--rr-neutral-mid)] sm:block">Platform-wide service provider control center</p>
                    </div>
                </header>
                <main className="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-10">
                    {section === 'overview' && <Overview url={overviewUrl} requestJson={requestJson} />}
                    {section === 'engagement' && <Engagement url={engagementUrl} requestJson={requestJson} />}
                    {section === 'settings' && <PlatformSettings url={settingsUrl} updateUrl={settingsUpdateUrl} requestJson={requestJson} />}
                    {section === 'businesses' && <Businesses url={businessesUrl} requestJson={requestJson} />}
                    {section === 'accounts' && <Accounts url={accountsUrl} requestJson={requestJson} exportUrl={exportUrl} />}
                    {section === null && <PageNotFound navigate={navigate} />}
                    {['memberships', 'content', 'reports', 'events', 'marketing', 'communications'].includes(section) && (
                        <AdminWorkflows
                            key={section}
                            section={section}
                            workspaceUrl={workspaceUrl}
                            urls={{
                                memberships: membershipsUrl,
                                resources: resourcesUrl,
                                reports: reportsUrl,
                                events: eventsUrl,
                                eventRegistrations: `${eventsUrl.replace(/\/events$/, '')}/event-registrations`,
                                marketing: marketingUrl,
                                services: servicesUrl,
                                orders: ordersUrl,
                                announcements: announcementsUrl,
                            }}
                            exportUrl={exportUrl}
                        />
                    )}
                    {['billing', 'analytics', 'audit'].includes(section) && (
                        <WorkspaceList key={section} section={section} url={workspaceUrl} requestJson={requestJson} />
                    )}
                    {section === 'orders' && <AdminWorkflows key="orders" section="orders" workspaceUrl={workspaceUrl} urls={{ orders: ordersUrl }} exportUrl={exportUrl} />}
                    {section === 'orders' && <AdminWorkflows key="services" section="services" workspaceUrl={workspaceUrl} urls={{ services: servicesUrl }} exportUrl={exportUrl} />}
                    {section && !availableSections.has(section) && <PlannedSection title={title} />}
                    <footer className="border-t border-[var(--rr-border)] pt-4 text-xs leading-5 text-[var(--rr-neutral-mid)]">
                        Financial totals reflect recorded order data only. Membership registration placeholders are not actual payments.
                    </footer>
                </main>
            </div>
        </div>
    );
}

function useRemoteData(load, deps) {
    const [state, setState] = useState({ loading: true, error: '', data: null });
    const refresh = useCallback(async () => {
        setState((current) => ({ ...current, loading: true, error: '' }));
        try {
            setState({ loading: false, error: '', data: await load() });
        } catch (error) {
            setState((current) => ({ ...current, loading: false, error: error.message || 'Unable to load data.' }));
        }
    }, deps);

    useEffect(() => { refresh(); }, [refresh]);
    return { ...state, refresh, setState };
}

function Overview({ url, requestJson }) {
    const { loading, error, data, refresh } = useRemoteData(() => requestJson(url), [url, requestJson]);

    if (loading && !data) return <Loading />;
    if (error && !data) return <ErrorState message={error} onRetry={refresh} />;
    const metrics = data.metrics;
    const cards = [
        ['Businesses', metrics.businesses_total, `${metrics.businesses_published} published · ${metrics.businesses_unpublished} unpublished`],
        ['Member accounts', metrics.regular_accounts, `${metrics.super_admins} service provider accounts · ${metrics.new_accounts_30_days} new in 30 days`],
        ['Active memberships', data.membership_statuses.active ?? 0, 'Counts use membership records'],
        ['Incomplete profiles', metrics.incomplete_businesses, 'Missing description, contact email, or website'],
        ['Upcoming events', metrics.upcoming_events_30_days, `${metrics.event_registrations} active registrations total`],
        ['Promotions', metrics.scheduled_promotions, `${metrics.completed_promotions_30_days} completed in the last 30 days`],
        ['Pending orders', metrics.pending_orders, 'Submitted, review, approved, or in-progress'],
        ['Recorded order payments', moneyList(metrics.confirmed_order_payments), `${moneyList(metrics.outstanding_order_balance)} outstanding order balance`],
    ];

    return (
        <>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">Organization overview</p>
                    <h2 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Initiative at a glance</h2>
                    <p className="mt-2 max-w-3xl text-sm leading-6 text-[var(--rr-neutral-mid)]">Live summaries from existing business, membership, event, marketing, order, and payment records.</p>
                </div>
                <button type="button" onClick={refresh} className="min-h-11 rounded-lg border border-[var(--rr-border)] bg-white px-4 text-sm font-semibold hover:bg-[var(--rr-neutral-light)]">Refresh</button>
            </div>
            {error && <InlineError>{error}</InlineError>}
            <section aria-label="Initiative metrics" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map(([label, value, note]) => <Metric key={label} label={label} value={value} note={note} />)}
            </section>
            <div className="grid gap-5 xl:grid-cols-2">
                <Panel title="Membership status" eyebrow="Current records">
                    {Object.keys(data.membership_statuses).length ? (
                        <div className="divide-y divide-[var(--rr-border)]">
                            {Object.entries(data.membership_statuses).map(([status, count]) => (
                                <div key={status} className="flex justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                    <span className="capitalize">{status.replaceAll('_', ' ')}</span><strong>{count}</strong>
                                </div>
                            ))}
                        </div>
                    ) : <Empty title="No membership records">Membership status totals will appear when records exist.</Empty>}
                </Panel>
                <Panel title="New membership records" eyebrow="Last 6 months">
                    <div className="space-y-4">
                        {data.membership_growth.map((month) => (
                            <div key={month.month} className="grid grid-cols-[5rem_minmax(0,1fr)_2rem] items-center gap-3 text-sm">
                                <span className="text-[var(--rr-neutral-mid)]">{month.month}</span>
                                <div className="h-2 overflow-hidden rounded-full bg-[var(--rr-neutral-light)]">
                                    <div className="h-full rounded-full bg-[var(--rr-accent)]" style={{ width: `${Math.min(month.new_memberships * 8, 100)}%` }} />
                                </div>
                                <span className="text-right font-semibold">{month.new_memberships}</span>
                            </div>
                        ))}
                    </div>
                </Panel>
                <Panel title="Recent administrative actions" eyebrow="Audit history" className="xl:col-span-2">
                    {data.recent_audit_logs.length ? (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[38rem] text-left text-sm">
                                <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]"><tr><th className="pb-3 font-semibold">Action</th><th className="pb-3 font-semibold">Actor</th><th className="pb-3 font-semibold">Record</th><th className="pb-3 font-semibold">Date</th></tr></thead>
                                <tbody className="divide-y divide-[var(--rr-border)]">
                                    {data.recent_audit_logs.map((entry) => <tr key={entry.id}><td className="py-3 font-medium">{entry.action}</td><td className="py-3">{entry.actor}</td><td className="py-3">{entry.target_type} {entry.target_id ?? ''}</td><td className="py-3 text-[var(--rr-neutral-mid)]">{date(entry.created_at)}</td></tr>)}
                                </tbody>
                            </table>
                        </div>
                    ) : <Empty title="No administrative actions recorded">Business publication changes and other audited actions will appear here.</Empty>}
                </Panel>
            </div>
            <Panel title="Metric definitions" eyebrow="Sources and caveats">
                <dl className="grid gap-4 md:grid-cols-2">
                    {Object.entries(data.metric_definitions).map(([key, definition]) => <div key={key}><dt className="text-sm font-semibold">{key.replaceAll('_', ' ')}</dt><dd className="mt-1 text-sm leading-6 text-[var(--rr-neutral-mid)]">{definition}</dd></div>)}
                </dl>
                <p className="mt-4 border-t border-[var(--rr-border)] pt-4 text-xs leading-5 text-[var(--rr-neutral-mid)]">No sales tax, referred website traffic, or independently verified business growth figures are shown because there is no approved source or calculation method currently connected.</p>
            </Panel>
        </>
    );
}

function Businesses({ url, requestJson }) {
    const [searchInput, setSearchInput] = useState('');
    const [filters, setFilters] = useState({ search: '', category: '', publication: '', active: '', membership_status: '', from: '', to: '', sort: 'name', page: 1 });
    const [notice, setNotice] = useState('');
    const [actionError, setActionError] = useState('');
    const [selectedBusiness, setSelectedBusiness] = useState(null);
    const [editingBusiness, setEditingBusiness] = useState(undefined);
    const [detailLoading, setDetailLoading] = useState(false);
    const params = useMemo(() => new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '')), [filters]);
    const { loading, error, data, refresh } = useRemoteData(() => requestJson(`${url}?${params}`), [url, params, requestJson]);

    async function setPublication(business, published) {
        if (published && !window.confirm(`Publish ${business.name} to the public business directory?`)) return;
        setNotice('');
        setActionError('');
        try {
            await requestJson(`${url}/${business.id}/publication`, {
                method: 'PATCH',
                body: JSON.stringify({ is_published: published }),
            });
            setNotice(`${business.name} ${published ? 'published' : 'unpublished'} successfully.`);
            await refresh();
        } catch (requestError) {
            setActionError(requestError.message || 'Could not update publication status.');
        }
    }

    async function showDetails(business) {
        setSelectedBusiness(null);
        setDetailLoading(true);
        setActionError('');
        try {
            const result = await requestJson(`${url}/${business.id}`);
            setSelectedBusiness(result.business);
        } catch (requestError) {
            setActionError(requestError.message || 'Could not load business details.');
        } finally {
            setDetailLoading(false);
        }
    }

    return (
        <>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <PageTitle eyebrow="Directory management" title="Member businesses" description="Search and review business profiles, associated account counts, membership status, and directory publication." />
                <div className="flex gap-2">
                    <button type="button" className={secondaryButton} onClick={async () => {
                        try {
                            const response = await fetch(`${document.getElementById('super-admin')?.dataset.exportUrl}/businesses`, {
                                method: 'POST', credentials: 'same-origin',
                                headers: { Accept: 'text/csv', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                                body: JSON.stringify(Object.fromEntries(Object.entries(filters).filter(([key]) => key !== 'page'))),
                            });
                            if (!response.ok) throw new Error('Business export failed.');
                            const blob = await response.blob();
                            const anchor = document.createElement('a');
                            anchor.href = URL.createObjectURL(blob);
                            anchor.download = 'businesses.csv';
                            anchor.click();
                            URL.revokeObjectURL(anchor.href);
                        } catch (requestError) { setActionError(requestError.message); }
                    }}>Export CSV</button>
                    <button type="button" className={primaryButton} onClick={() => { setEditingBusiness(null); setActionError(''); }}>Create business</button>
                </div>
            </div>
            {editingBusiness !== undefined && <BusinessEditor
                business={editingBusiness}
                key={editingBusiness?.id ?? 'new-business'}
                requestJson={requestJson}
                onReload={async () => {
                    if (!editingBusiness?.id) return;
                    const result = await requestJson(`${url}/${editingBusiness.id}`);
                    setEditingBusiness(result.business);
                    setSelectedBusiness(result.business);
                }}
                onCancel={() => setEditingBusiness(undefined)}
                onSave={async (values) => {
                    setActionError('');
                    setNotice('');
                    try {
                        const isUpdate = Boolean(editingBusiness?.id);
                        const saved = await requestJson(isUpdate ? `${url}/${editingBusiness.id}` : url, {
                            method: isUpdate ? 'PUT' : 'POST',
                            body: JSON.stringify(values),
                        });
                        setNotice(isUpdate ? 'Business profile updated.' : 'Business profile created.');
                        const result = await requestJson(`${url}/${saved.id ?? editingBusiness.id}`);
                        setEditingBusiness(result.business);
                        setSelectedBusiness(result.business);
                        await refresh();
                    } catch (requestError) {
                        setActionError(requestError.message);
                    }
                }}
            />}
            <Panel>
                <form onSubmit={(event) => {
                    event.preventDefault();
                    setFilters((current) => ({ ...current, search: searchInput.trim(), page: 1 }));
                }} className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <label className="block"><span className="mb-1 block text-xs font-semibold">Search</span><input value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder="Business, contact, or email" className={inputClass} /></label>
                    <Select label="Category" value={filters.category} onChange={(value) => setFilters((current) => ({ ...current, category: value, page: 1 }))} options={(data?.categories ?? []).map((item) => [item, item])} />
                    <Select label="Publication" value={filters.publication} onChange={(value) => setFilters((current) => ({ ...current, publication: value, page: 1 }))} options={[[ 'published', 'Published'], ['unpublished', 'Unpublished']]} />
                    <Select label="Business status" value={filters.active} onChange={(value) => setFilters((current) => ({ ...current, active: value, page: 1 }))} options={[[ 'active', 'Active'], ['inactive', 'Inactive']]} />
                    <Select label="Membership" value={filters.membership_status} onChange={(value) => setFilters((current) => ({ ...current, membership_status: value, page: 1 }))} options={['active', 'pending_activation', 'delinquent', 'cancelled', 'expired'].map((item) => [item, item.replaceAll('_', ' ')])} />
                    <label className="block"><span className="mb-1 block text-xs font-semibold">Created from</span><input type="date" value={filters.from} onChange={(event) => setFilters((current) => ({ ...current, from: event.target.value, page: 1 }))} className={inputClass} /></label>
                    <label className="block"><span className="mb-1 block text-xs font-semibold">Created to</span><input type="date" value={filters.to} onChange={(event) => setFilters((current) => ({ ...current, to: event.target.value, page: 1 }))} className={inputClass} /></label>
                    <div className="flex items-end gap-2"><Select label="Sort by" value={filters.sort} onChange={(value) => setFilters((current) => ({ ...current, sort: value || 'name', page: 1 }))} options={[[ 'name', 'Name'], ['newest', 'Newest'], ['oldest', 'Oldest']]} /><button type="submit" className={primaryButton}>Apply</button></div>
                </form>
            </Panel>
            {notice && <div role="status" className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">{notice}</div>}
            {actionError && <InlineError>{actionError}</InlineError>}
            {detailLoading && <p role="status" className="text-sm text-[var(--rr-neutral-mid)]">Loading business details…</p>}
            {selectedBusiness && <BusinessDetail business={selectedBusiness} onClose={() => setSelectedBusiness(null)} onEdit={() => setEditingBusiness(selectedBusiness)} />}
            {loading && !data ? <Loading /> : error && !data ? <ErrorState message={error} onRetry={refresh} /> : (
                <Panel title={`${data.meta.total} businesses`} eyebrow="Directory">
                    {data.data.length ? (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[60rem] text-left text-sm">
                                    <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]"><tr>
                                        <th className="pb-3 pr-4 font-semibold">Business</th><th className="pb-3 pr-4 font-semibold">Contact</th><th className="pb-3 pr-4 font-semibold">Membership</th><th className="pb-3 pr-4 font-semibold">Accounts</th><th className="pb-3 pr-4 font-semibold">Directory</th><th className="pb-3 font-semibold">Actions</th>
                                    </tr></thead>
                                    <tbody className="divide-y divide-[var(--rr-border)]">
                                        {data.data.map((business) => <tr key={business.id}>
                                            <td className="py-4 pr-4"><p className="font-semibold">{business.name}</p><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Business ID #{business.id} · {[business.category, business.city, business.state].filter(Boolean).join(' · ') || 'Details incomplete'}</p></td>
                                            <td className="py-4 pr-4"><p>{business.contact_name || 'No contact name'}</p><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{business.contact_email || 'No email'}</p></td>
                                            <td className="py-4 pr-4"><p className="capitalize">{business.membership?.status?.replaceAll('_', ' ') ?? 'None'}</p><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{business.membership?.plan ?? ''}</p></td>
                                            <td className="py-4 pr-4">{business.members_count}</td>
                                            <td className="py-4 pr-4"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${business.is_published ? 'bg-[var(--rr-accent-light)] text-[var(--rr-accent)]' : 'bg-[var(--rr-neutral-light)] text-[var(--rr-neutral-dark)]'}`}>{business.is_published ? 'Published' : 'Unpublished'}</span></td>
                                            <td className="py-4"><div className="flex gap-2"><button type="button" onClick={() => showDetails(business)} className="min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)]">Details</button><button type="button" onClick={() => setPublication(business, !business.is_published)} className="min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)]">{business.is_published ? 'Unpublish' : 'Publish'}</button></div></td>
                                        </tr>)}
                                    </tbody>
                                </table>
                            </div>
                            <Pagination meta={data.meta} onPage={(page) => setFilters((current) => ({ ...current, page }))} />
                        </>
                    ) : <Empty title="No businesses match">Try changing or clearing the selected search and filters.</Empty>}
                </Panel>
            )}
        </>
    );
}

function BusinessDetail({ business, onClose, onEdit }) {
    return (
        <Panel title={business.name} eyebrow="Business record" className="border-[var(--rr-primary)]">
            <div className="flex flex-wrap justify-between gap-3">
                <div className="flex flex-wrap gap-2">
                    <Status tone={business.is_active ? 'success' : 'neutral'}>{business.is_active ? 'Active' : 'Inactive'}</Status>
                    <Status tone={business.is_published ? 'success' : 'neutral'}>{business.is_published ? 'Published' : 'Unpublished'}</Status>
                </div>
                <div className="flex gap-2"><button type="button" onClick={onEdit} className={primaryButton}>Edit business</button><button type="button" onClick={onClose} className={secondaryButton}>Close details</button></div>
            </div>
            <dl className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Definition label="Category" value={business.category} />
                <Definition label="Business ID" value={business.id} />
                <Definition label="Contact" value={business.contact_name} />
                <Definition label="Email" value={business.contact_email} />
                <Definition label="Phone" value={business.contact_phone} />
                <Definition label="Website" value={business.website} />
                <Definition label="Address" value={[business.address_line_1, business.address_line_2, business.city, business.state, business.postal_code].filter(Boolean).join(', ')} />
                <Definition label="Business status" value={business.status} />
                <Definition label="Directory visibility" value={business.directory_visibility} />
            </dl>
            <div className="mt-6 grid gap-6 border-t border-[var(--rr-border)] pt-5 lg:grid-cols-2">
                <div>
                    <h3 className="font-semibold">Associated accounts</h3>
                    {business.members.length ? <ul className="mt-3 divide-y divide-[var(--rr-border)]">{business.members.map((member) => <li key={member.id} className="py-3 first:pt-0"><p className="text-sm font-medium">{member.name} <span className="font-normal text-[var(--rr-neutral-mid)]">· {member.role}</span></p><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{member.email}{member.account_type === 'super_admin' ? ' · Service provider' : ''}</p></li>)}</ul> : <p className="mt-2 text-sm text-[var(--rr-neutral-mid)]">No active accounts are associated.</p>}
                </div>
                <div>
                    <h3 className="font-semibold">Membership history</h3>
                    {business.memberships.length ? <ul className="mt-3 divide-y divide-[var(--rr-border)]">{business.memberships.map((membership) => <li key={membership.id} className="py-3 first:pt-0"><p className="text-sm font-medium">{membership.plan || 'Membership plan'} · <span className="capitalize">{membership.status.replaceAll('_', ' ')}</span></p><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Payment status: {membership.payment_is_simulated ? 'simulated placeholder (no transaction)' : membership.payment_status} · Start: {membership.start_date || 'not recorded'} · Renewal: {membership.end_date || 'not set'}</p></li>)}</ul> : <p className="mt-2 text-sm text-[var(--rr-neutral-mid)]">No membership records.</p>}
                    <h3 className="mt-5 font-semibold">Recorded activity counts</h3>
                    <p className="mt-2 text-sm text-[var(--rr-neutral-mid)]">Reports {business.activity.reports} · Events {business.activity.events} · Promotions {business.activity.marketing_activities} · Orders {business.activity.orders}</p>
                </div>
            </div>
        </Panel>
    );
}

function BusinessEditor({ business, onCancel, onSave, requestJson, onReload }) {
    const [mediaError, setMediaError] = useState('');
    const [mediaNotice, setMediaNotice] = useState('');
    const [videoType, setVideoType] = useState(business?.video?.source_type ?? 'youtube');
    const [highlights, setHighlights] = useState(business?.highlights ?? []);
    const values = {
        company_name: business?.name ?? '',
        category: business?.category ?? '',
        additional_categories: (business?.categories ?? []).map((category) => typeof category === 'string' ? category : category.name).filter((category) => category !== business?.category).join(', '),
        tagline: business?.tagline ?? '',
        short_description: business?.short_description ?? '',
        description: business?.description ?? '',
        year_established: business?.year_established ?? '',
        employee_count: business?.employee_count ?? '',
        ownership_information: business?.ownership_information ?? '',
        contact_name: business?.contact_name ?? '',
        contact_email: business?.contact_email ?? '',
        contact_phone: business?.contact_phone ?? '',
        alternate_phone: business?.alternate_phone ?? '',
        website: business?.website ?? '',
        facebook_url: business?.facebook_url ?? '',
        instagram_url: business?.instagram_url ?? '',
        linkedin_url: business?.linkedin_url ?? '',
        tiktok_url: business?.tiktok_url ?? '',
        youtube_url: business?.youtube_url ?? '',
        other_social_url: business?.other_social_url ?? '',
        address_line_1: business?.address_line_1 ?? '',
        address_line_2: business?.address_line_2 ?? '',
        city: business?.city ?? '',
        state: business?.state ?? '',
        postal_code: business?.postal_code ?? '',
        country: business?.country ?? 'US',
        latitude: business?.latitude ?? '',
        longitude: business?.longitude ?? '',
        specialties: (business?.specialties ?? []).join(', '),
        accessibility_information: business?.accessibility_information ?? '',
        appointment_methods: (business?.appointment_methods ?? []).join(', '),
        service_areas: (business?.service_areas ?? []).join(', '),
        meta_title: business?.meta_title ?? '',
        meta_description: business?.meta_description ?? '',
        is_active: business?.is_active ?? true,
        is_featured: business?.is_featured ?? false,
        directory_visibility: business?.directory_visibility ?? 'private',
    };
    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const currentHours = new Map((business?.hours ?? []).map((hour) => [Number(hour.day_of_week), hour]));

    async function uploadImages(event) {
        event.preventDefault();
        const formElement = event.currentTarget;
        const form = new FormData(formElement);
        const files = form.getAll('images').filter((file) => file instanceof File && file.size > 0);
        const altText = form.get('alt_text')?.toString() ?? '';
        const payload = new FormData();
        files.forEach((file) => payload.append('images[]', file));
        files.forEach(() => payload.append('alt_text[]', altText));
        try {
            const response = await requestJson(`/super-admin/api/businesses/${business.id}/images`, { method: 'POST', body: payload });
            setMediaNotice(`${response.data.length} image${response.data.length === 1 ? '' : 's'} uploaded.`);
            formElement.reset();
            await onReload();
        } catch (error) {
            setMediaError(error.message);
        }
    }

    async function updateImage(image, changes) {
        try {
            await requestJson(`/super-admin/api/businesses/${business.id}/images/${image.id}`, {
                method: 'PUT',
                body: JSON.stringify(changes),
            });
            await onReload();
        } catch (error) {
            setMediaError(error.message);
        }
    }

    async function removeImage(image) {
        if (!window.confirm('Remove this image from the business profile?')) return;
        try {
            await requestJson(`/super-admin/api/businesses/${business.id}/images/${image.id}`, { method: 'DELETE' });
            setMediaNotice('Image removed.');
            await onReload();
        } catch (error) {
            setMediaError(error.message);
        }
    }

    async function moveImage(images, index, direction) {
        const otherIndex = index + direction;
        if (otherIndex < 0 || otherIndex >= images.length) return;
        const current = images[index];
        const other = images[otherIndex];
        try {
            await requestJson(`/super-admin/api/businesses/${business.id}/images/${current.id}`, {
                method: 'PUT',
                body: JSON.stringify({ sort_order: other.sort_order }),
            });
            await requestJson(`/super-admin/api/businesses/${business.id}/images/${other.id}`, {
                method: 'PUT',
                body: JSON.stringify({ sort_order: current.sort_order }),
            });
            await onReload();
        } catch (error) {
            setMediaError(error.message);
        }
    }

    async function saveVideo(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        form.set('source_type', videoType);
        try {
            await requestJson(`/super-admin/api/businesses/${business.id}/video`, { method: 'POST', body: form });
            setMediaNotice('Business video saved.');
            await onReload();
        } catch (error) {
            setMediaError(error.message);
        }
    }

    async function removeVideo() {
        try {
            await requestJson(`/super-admin/api/businesses/${business.id}/video`, { method: 'DELETE' });
            setVideoType('youtube');
            setMediaNotice('Business video removed.');
            await onReload();
        } catch (error) {
            setMediaError(error.message);
        }
    }

    return (
        <Panel title={business ? `Edit ${business.name}` : 'Create business profile'}>
            <form onSubmit={(event) => {
                event.preventDefault();
                const form = new FormData(event.currentTarget);
                const payload = Object.fromEntries(form.entries());
                payload.is_active = form.has('is_active');
                payload.is_featured = form.has('is_featured');
                payload.additional_categories = form.get('additional_categories').split(',').map((item) => item.trim()).filter(Boolean);
                payload.specialties = form.get('specialties').split(',').map((item) => item.trim()).filter(Boolean);
                payload.appointment_methods = form.get('appointment_methods').split(',').map((item) => item.trim()).filter(Boolean);
                payload.service_areas = form.get('service_areas').split(',').map((item) => item.trim()).filter(Boolean);
                payload.hours = dayNames.map((_, day) => ({
                    day_of_week: day,
                    opens_at: form.get(`opens_at_${day}`) || null,
                    closes_at: form.get(`closes_at_${day}`) || null,
                    is_closed: form.has(`closed_${day}`),
                    by_appointment: form.has(`appointment_${day}`),
                })).filter((hour) => hour.is_closed || hour.by_appointment || hour.opens_at || hour.closes_at);
                payload.highlights = highlights.filter((highlight) => highlight.name?.trim()).map((highlight) => ({
                    name: highlight.name.trim(),
                    description: highlight.description ?? '',
                    price_information: highlight.price_information ?? '',
                    is_active: Boolean(highlight.is_active),
                }));
                onSave(payload);
            }} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <FormInput name="company_name" label="Business name" required initial={values.company_name} />
                <FormInput name="category" label="Category" required initial={values.category} />
                <FormInput name="additional_categories" label="Additional categories (comma separated)" initial={values.additional_categories} />
                <FormInput name="tagline" label="Tagline" initial={values.tagline} />
                <FormInput name="short_description" label="Short description" initial={values.short_description} />
                <FormInput name="year_established" label="Year established" type="number" initial={values.year_established} />
                <FormInput name="employee_count" label="Employee count" initial={values.employee_count} />
                <FormInput name="contact_email" label="Contact email" type="email" initial={values.contact_email} />
                <FormInput name="contact_name" label="Contact name" initial={values.contact_name} />
                <FormInput name="contact_phone" label="Contact phone" initial={values.contact_phone} />
                <FormInput name="alternate_phone" label="Alternate phone" initial={values.alternate_phone} />
                <FormInput name="website" label="Website" type="url" initial={values.website} />
                <FormInput name="facebook_url" label="Facebook URL" type="url" initial={values.facebook_url} />
                <FormInput name="instagram_url" label="Instagram URL" type="url" initial={values.instagram_url} />
                <FormInput name="linkedin_url" label="LinkedIn URL" type="url" initial={values.linkedin_url} />
                <FormInput name="tiktok_url" label="TikTok URL" type="url" initial={values.tiktok_url} />
                <FormInput name="youtube_url" label="YouTube URL" type="url" initial={values.youtube_url} />
                <FormInput name="other_social_url" label="Other social URL" type="url" initial={values.other_social_url} />
                <FormInput name="address_line_1" label="Address" initial={values.address_line_1} />
                <FormInput name="address_line_2" label="Address line 2" initial={values.address_line_2} />
                <FormInput name="city" label="City" initial={values.city} />
                <FormInput name="state" label="State" initial={values.state} />
                <FormInput name="postal_code" label="Postal code" initial={values.postal_code} />
                <FormInput name="country" label="Country (ISO code)" required initial={values.country} />
                <FormInput name="latitude" label="Latitude" type="number" initial={values.latitude} />
                <FormInput name="longitude" label="Longitude" type="number" initial={values.longitude} />
                <FormInput name="specialties" label="Specialties (comma separated)" initial={values.specialties} />
                <FormInput name="appointment_methods" label="Appointment methods (comma separated)" initial={values.appointment_methods} />
                <FormInput name="service_areas" label="Service areas (comma separated)" initial={values.service_areas} />
                <label className="block text-xs font-semibold">Directory visibility
                    <select name="directory_visibility" defaultValue={values.directory_visibility} className={`${inputClass} mt-1`}>
                        <option value="private">Private</option><option value="members">Members</option><option value="public">Public</option>
                    </select>
                </label>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" defaultChecked={values.is_active} className="min-h-5 min-w-5 accent-[var(--rr-primary)]" />Active business</label>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" defaultChecked={values.is_featured} className="min-h-5 min-w-5 accent-[var(--rr-primary)]" />Feature on the homepage</label>
                <label className="block text-xs font-semibold sm:col-span-2 lg:col-span-3">Description<textarea name="description" defaultValue={values.description} rows="4" className={`${inputClass} mt-1`} /></label>
                <label className="block text-xs font-semibold sm:col-span-2 lg:col-span-3">Ownership information<textarea name="ownership_information" defaultValue={values.ownership_information} rows="2" className={`${inputClass} mt-1`} /></label>
                <label className="block text-xs font-semibold sm:col-span-2 lg:col-span-3">Accessibility information<textarea name="accessibility_information" defaultValue={values.accessibility_information} rows="2" className={`${inputClass} mt-1`} /></label>
                <div className="sm:col-span-2 lg:col-span-3">
                    <h3 className="mb-2 text-sm font-semibold">Business hours</h3>
                    <div className="space-y-2">
                        {dayNames.map((dayName, day) => {
                            const hour = currentHours.get(day) ?? {};
                            return <div key={dayName} className="grid items-center gap-2 rounded-lg border border-[var(--rr-border)] p-3 sm:grid-cols-[6rem_1fr_1fr_auto_auto]">
                                <span className="text-sm font-medium">{dayName}</span>
                                <label className="text-xs">Opens<input type="time" name={`opens_at_${day}`} defaultValue={hour.opens_at ?? ''} className={`${inputClass} mt-1`} /></label>
                                <label className="text-xs">Closes<input type="time" name={`closes_at_${day}`} defaultValue={hour.closes_at ?? ''} className={`${inputClass} mt-1`} /></label>
                                <label className="flex items-center gap-1 text-xs"><input type="checkbox" name={`closed_${day}`} defaultChecked={Boolean(hour.is_closed)} />Closed</label>
                                <label className="flex items-center gap-1 text-xs"><input type="checkbox" name={`appointment_${day}`} defaultChecked={Boolean(hour.by_appointment)} />By appointment</label>
                            </div>;
                        })}
                    </div>
                </div>
                <div className="sm:col-span-2 lg:col-span-3">
                    <div className="mb-2 flex items-center justify-between"><h3 className="text-sm font-semibold">Profile highlights</h3><button type="button" className={secondaryButton} onClick={() => setHighlights((current) => [...current, { name: '', description: '', price_information: '', is_active: true }])}>Add highlight</button></div>
                    <div className="space-y-3">
                        {highlights.map((highlight, index) => <div key={highlight.id ?? `new-${index}`} className="grid gap-2 rounded-lg border border-[var(--rr-border)] p-3 sm:grid-cols-2">
                            <input aria-label="Highlight name" placeholder="Highlight name" value={highlight.name ?? ''} onChange={(event) => setHighlights((items) => items.map((item, itemIndex) => itemIndex === index ? { ...item, name: event.target.value } : item))} className={inputClass} />
                            <input aria-label="Price information" placeholder="Price information" value={highlight.price_information ?? ''} onChange={(event) => setHighlights((items) => items.map((item, itemIndex) => itemIndex === index ? { ...item, price_information: event.target.value } : item))} className={inputClass} />
                            <textarea aria-label="Highlight description" placeholder="Description" value={highlight.description ?? ''} onChange={(event) => setHighlights((items) => items.map((item, itemIndex) => itemIndex === index ? { ...item, description: event.target.value } : item))} className={`${inputClass} sm:col-span-2`} />
                            <button type="button" className="w-fit text-xs font-semibold text-[var(--rr-error)]" onClick={() => setHighlights((items) => items.filter((_, itemIndex) => itemIndex !== index))}>Remove highlight</button>
                        </div>)}
                    </div>
                </div>
                <FormInput name="meta_title" label="SEO title" initial={values.meta_title} />
                <label className="block text-xs font-semibold sm:col-span-2">SEO description<textarea name="meta_description" defaultValue={values.meta_description} rows="2" className={`${inputClass} mt-1`} /></label>
                <div className="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3"><button className={primaryButton}>{business ? 'Save profile details' : 'Create business'}</button>{business?.id && <a className={secondaryButton} target="_blank" rel="noreferrer" href={`/super-admin/businesses/${business.id}/preview`}>Preview profile</a>}<button type="button" onClick={onCancel} className={secondaryButton}>Close editor</button></div>
            </form>
            {business?.id && <>
                <div className="mt-7 border-t border-[var(--rr-border)] pt-6">
                    <div className="flex flex-wrap items-center justify-between gap-3"><div><h3 className="font-semibold">Business gallery</h3><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{business.images?.length ?? 0} of 10 images · at least 2 images are required to publish.</p></div></div>
                    {mediaError && <div className="mt-3"><InlineError>{mediaError}</InlineError></div>}
                    {mediaNotice && <p role="status" className="mt-3 text-sm text-[var(--rr-accent)]">{mediaNotice}</p>}
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {(business.images ?? []).map((image, index) => <article key={image.id} className="overflow-hidden rounded-xl border border-[var(--rr-border)]">
                            <img src={image.url} alt={image.alt_text || ''} className="aspect-[4/3] w-full object-cover" />
                            <div className="space-y-2 p-3">
                                <label className="block text-xs">Alt text<input defaultValue={image.alt_text ?? ''} onBlur={(event) => updateImage(image, { alt_text: event.target.value })} className={`${inputClass} mt-1`} /></label>
                                <div className="flex flex-wrap gap-2">
                                    <button type="button" onClick={() => updateImage(image, { is_primary: true })} className={secondaryButton}>{image.is_primary ? 'Primary image' : 'Make primary'}</button>
                                    <button type="button" disabled={index === 0} onClick={() => moveImage(business.images, index, -1)} className={secondaryButton}>Move up</button>
                                    <button type="button" disabled={index === business.images.length - 1} onClick={() => moveImage(business.images, index, 1)} className={secondaryButton}>Move down</button>
                                    <button type="button" onClick={() => removeImage(image)} className="min-h-10 rounded-lg px-3 text-xs font-semibold text-[var(--rr-error)]">Remove</button>
                                </div>
                            </div>
                        </article>)}
                    </div>
                    <form onSubmit={uploadImages} className="mt-4 grid gap-3 rounded-xl border border-dashed border-[var(--rr-border)] p-4 sm:grid-cols-[1fr_1fr_auto]">
                        <label className="block text-xs font-semibold">Add images (JPEG, PNG, or WebP; 10 MB max each)<input type="file" name="images" accept="image/jpeg,image/png,image/webp" multiple required className={`${inputClass} mt-1`} /></label>
                        <label className="block text-xs font-semibold">Alt text for uploaded images<input name="alt_text" maxLength="255" className={`${inputClass} mt-1`} /></label>
                        <button className={primaryButton}>Upload images</button>
                    </form>
                </div>
                <div className="mt-7 border-t border-[var(--rr-border)] pt-6">
                    <h3 className="font-semibold">Business video</h3>
                    {business.video?.video_url && <video controls src={business.video.video_url} className="mt-3 max-h-64 w-full rounded-lg bg-black" />}
                    {business.video?.source_type === 'youtube' && <p className="mt-2 break-all text-sm text-[var(--rr-neutral-mid)]">{business.video.source_url}</p>}
                    <form onSubmit={saveVideo} className="mt-3 grid gap-3 sm:grid-cols-2">
                        <label className="block text-xs font-semibold">Video source<select value={videoType} onChange={(event) => setVideoType(event.target.value)} className={`${inputClass} mt-1`}><option value="youtube">YouTube</option><option value="external">External HTTPS video file</option><option value="upload">Upload video</option></select></label>
                        {videoType === 'youtube' && <FormInput name="youtube_url" label="YouTube URL" initial={business.video?.source_type === 'youtube' ? business.video.source_url : ''} />}
                        {videoType === 'external' && <FormInput name="external_url" label="External HTTPS video URL" type="url" initial={business.video?.source_type === 'external' ? business.video.source_url : ''} />}
                        {videoType === 'upload' && <label className="block text-xs font-semibold">Video file (MP4 or WebM, 10 MB max)<input name="video_file" type="file" accept="video/mp4,video/webm" className={`${inputClass} mt-1`} /></label>}
                        <div className="flex items-end gap-2"><button className={primaryButton}>Save video</button>{business.video && <button type="button" className={secondaryButton} onClick={removeVideo}>Remove video</button>}</div>
                    </form>
                </div>
            </>}
        </Panel>
    );
}

function FormInput({ name, label, type = 'text', initial = '', required = false }) {
    return <label className="block text-xs font-semibold">{label}<input className={`${inputClass} mt-1`} type={type} name={name} defaultValue={initial} required={required} /></label>;
}

function Accounts({ url, requestJson, exportUrl }) {
    const [searchInput, setSearchInput] = useState('');
    const [filters, setFilters] = useState({ search: '', account_type: '', page: 1 });
    const [exportError, setExportError] = useState('');
    const params = useMemo(() => new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '')), [filters]);
    const { loading, error, data, refresh } = useRemoteData(() => requestJson(`${url}?${params}`), [url, params, requestJson]);

    return (
        <>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <PageTitle eyebrow="Access directory" title="Members and accounts" description="Review registered accounts, their business associations, and account type. Permission changes are intentionally not exposed here." />
                <button type="button" className={secondaryButton} onClick={async () => {
                    try {
                        const response = await fetch(`${exportUrl}/members`, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { Accept: 'text/csv', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                            body: JSON.stringify({ search: filters.search }),
                        });
                        if (!response.ok) throw new Error('Member export failed.');
                        const blob = await response.blob();
                        const anchor = document.createElement('a');
                        anchor.href = URL.createObjectURL(blob);
                        anchor.download = 'members.csv';
                        anchor.click();
                        URL.revokeObjectURL(anchor.href);
                    } catch (requestError) { setExportError(requestError.message); }
                }}>Export members CSV</button>
            </div>
            {exportError && <InlineError>{exportError}</InlineError>}
            <Panel>
                <form onSubmit={(event) => {
                    event.preventDefault();
                    setFilters((current) => ({ ...current, search: searchInput.trim(), page: 1 }));
                }} className="flex flex-col gap-3 sm:flex-row">
                    <label htmlFor="account-search" className="sr-only">Search accounts</label>
                    <input id="account-search" value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder="Name or email address" className={`${inputClass} flex-1`} />
                    <Select label="Account type" value={filters.account_type} onChange={(value) => setFilters((current) => ({ ...current, account_type: value, page: 1 }))} options={[[UserType.regular, 'Regular account'], [UserType.admin, 'Service provider']]} />
                    <button type="submit" className={primaryButton}>Search</button>
                </form>
            </Panel>
            {loading && !data ? <Loading /> : error && !data ? <ErrorState message={error} onRetry={refresh} /> : (
                <Panel title={`${data.meta.total} accounts`} eyebrow="Registered users">
                    {data.data.length ? <>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[42rem] text-left text-sm">
                                <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]"><tr><th className="pb-3 pr-4 font-semibold">Member</th><th className="pb-3 pr-4 font-semibold">Account type</th><th className="pb-3 pr-4 font-semibold">Business associations</th><th className="pb-3 font-semibold">Joined</th></tr></thead>
                                <tbody className="divide-y divide-[var(--rr-border)]">
                                    {data.data.map((account) => <tr key={account.id}>
                                        <td className="py-4 pr-4"><p className="font-semibold">{account.name}</p><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Account ID #{account.id} · {account.email}</p></td>
                                        <td className="py-4 pr-4">{account.account_type === UserType.admin ? 'Service provider' : 'Regular account'}{!account.is_active && <span className="ml-2 text-xs text-[var(--rr-error)]">Inactive</span>}</td>
                                        <td className="py-4 pr-4">{account.businesses.length ? account.businesses.map((business) => business.name).join(', ') : 'No active business'}</td>
                                        <td className="py-4 text-[var(--rr-neutral-mid)]">{date(account.created_at)}</td>
                                    </tr>)}
                                </tbody>
                            </table>
                        </div>
                        <Pagination meta={data.meta} onPage={(page) => setFilters((current) => ({ ...current, page }))} />
                    </> : <Empty title="No accounts match">Try a different search or account-type filter.</Empty>}
                </Panel>
            )}
        </>
    );
}

const listColumns = {
    memberships: [['business_name', 'Business'], ['plan', 'Plan'], ['status', 'Membership status'], ['payment_status', 'Payment status'], ['start_date', 'Start date'], ['end_date', 'End date']],
    content: [['title', 'Resource'], ['business_name', 'Business'], ['resource_type', 'Type'], ['status', 'Status'], ['visibility', 'Visibility'], ['file_name', 'File'], ['published_at', 'Published']],
    reports: [['title', 'Report'], ['business_name', 'Business'], ['creator_name', 'Submitted by'], ['reporting_period', 'Reporting period'], ['status', 'Status'], ['visibility', 'Visibility'], ['file_name', 'File'], ['submitted_at', 'Submitted'], ['review_feedback', 'Feedback'], ['published_at', 'Published']],
    events: [['title', 'Event'], ['business_name', 'Business'], ['status', 'Status'], ['is_published', 'Published'], ['starts_at', 'Starts'], ['active_registrations', 'Registrations'], ['capacity', 'Capacity']],
    marketing: [['title', 'Activity'], ['business_name', 'Business'], ['status', 'Status'], ['scheduled_at', 'Scheduled'], ['assigned_staff', 'Assigned staff'], ['description', 'Description']],
    orders: [['order_number', 'Order'], ['business_name', 'Business'], ['member_name', 'Member'], ['status', 'Order status'], ['payment_status', 'Payment status'], ['total', 'Total'], ['submitted_at', 'Submitted']],
    services: [['name', 'Service'], ['price', 'Price'], ['status', 'Status'], ['featured', 'Featured'], ['deleted', 'Archived']],
    billing: [['order_number', 'Order'], ['business_name', 'Business'], ['amount', 'Amount'], ['currency', 'Currency'], ['payment_date', 'Payment date'], ['method', 'Method'], ['status', 'Status'], ['recorded_by', 'Recorded by']],
    analytics: [['business_name', 'Business'], ['metric_type', 'Metric'], ['value', 'Reported value'], ['unit', 'Unit'], ['period_start', 'Period start'], ['period_end', 'Period end'], ['source', 'Source']],
    audit: [['actor', 'Actor'], ['action', 'Action'], ['record_type', 'Record'], ['record_id', 'Record ID'], ['created_at', 'Date']],
};

function WorkspaceList({ section, url, requestJson, title }) {
    const [filters, setFilters] = useState({ page: 1, search: '', status: '', business_id: '' });
    const [searchInput, setSearchInput] = useState('');
    const [exportError, setExportError] = useState('');
    const params = useMemo(() => new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '')), [filters]);
    const { loading, error, data, refresh } = useRemoteData(() => requestJson(`${url}/${section}?${params}`), [url, section, params, requestJson]);
    const columns = listColumns[section] ?? [];
    const pageTitle = title ?? navigation.find(([key]) => key === section)?.[1] ?? section;
    const descriptions = {
        memberships: 'Membership records and their separate recorded payment status. No payment status is inferred from membership status.',
        content: 'Published and unpublished general resources. Stored file paths are never exposed by this endpoint.',
        reports: 'Business reports and their visibility state. This view does not expose document paths or provide downloads.',
        events: 'Events across businesses with publication state and active registration counts.',
        marketing: 'Manually scheduled and completed marketing activity. No automatic social posting is performed.',
        orders: 'Existing order records and historical item prices. Fulfillment updates are not implemented here.',
        services: 'Current and archived service catalog entries. Editing the catalog is not implemented here.',
        billing: 'Recorded order payments only. Membership payment transactions are not available in the current schema.',
        analytics: 'Reported business metrics with their original period and source. Values are not independently verified.',
        audit: 'Recent administrative and system audit entries.',
    };

    if (loading && !data) return <Loading />;
    if (error && !data) return <ErrorState message={error} onRetry={refresh} />;

    return (
        <>
            <PageTitle eyebrow="Read-only administration" title={pageTitle} description={descriptions[section]} />
            <Panel>
                <form onSubmit={(event) => { event.preventDefault(); setFilters((current) => ({ ...current, search: searchInput.trim(), page: 1 })); }} className="grid gap-3 sm:grid-cols-4">
                    <label className="text-xs font-semibold">Search<input className={`${inputClass} mt-1`} value={searchInput} onChange={(event) => setSearchInput(event.target.value)} /></label>
                    <label className="text-xs font-semibold">Status<select className={`${inputClass} mt-1`} value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value, page: 1 }))}><option value="">All statuses</option>{(section === 'billing' ? ['confirmed', 'pending', 'failed', 'reversed'] : []).map((status) => <option key={status}>{status}</option>)}</select></label>
                    <label className="text-xs font-semibold">Business ID<input className={`${inputClass} mt-1`} value={filters.business_id} onChange={(event) => setFilters((current) => ({ ...current, business_id: event.target.value, page: 1 }))} /></label>
                    <div className="flex items-end gap-2"><button className={primaryButton}>Apply filters</button>{section === 'billing' && <button type="button" className={secondaryButton} onClick={async () => {
                        setExportError('');
                        try {
                            const { page: ignoredPage, ...exportFilters } = filters;
                            const response = await fetch(`${document.getElementById('super-admin')?.dataset.exportUrl}/payments`, {
                                method: 'POST', credentials: 'same-origin',
                                headers: { Accept: 'text/csv', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                                body: JSON.stringify(exportFilters),
                            });
                            if (!response.ok) throw new Error('Recorded payment export failed.');
                            const blob = await response.blob();
                            const anchor = document.createElement('a');
                            anchor.href = URL.createObjectURL(blob);
                            anchor.download = 'recorded-order-payments.csv';
                            anchor.click();
                            URL.revokeObjectURL(anchor.href);
                        } catch (requestError) { setExportError(requestError.message); }
                    }}>Export recorded payments</button>}</div>
                </form>
                {exportError && <div className="mt-3"><InlineError>{exportError}</InlineError></div>}
            </Panel>
            <Panel title={`${data.meta.total} records`} eyebrow="Database records">
                {data.data.length ? <>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[48rem] text-left text-sm">
                            <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]"><tr>{columns.map(([field, label]) => <th key={field} className="pb-3 pr-4 font-semibold">{label}</th>)}</tr></thead>
                            <tbody className="divide-y divide-[var(--rr-border)]">
                                {data.data.map((row) => <tr key={row.id}>{columns.map(([field]) => <td key={field} className="max-w-72 py-3 pr-4 align-top">{displayField(field, row[field], row)}</td>)}</tr>)}
                            </tbody>
                        </table>
                    </div>
                    <Pagination meta={data.meta} onPage={(page) => setFilters((current) => ({ ...current, page }))} />
                </> : <Empty title={`No ${pageTitle.toLowerCase()} found`}>Records will appear here when they exist in the database.</Empty>}
            </Panel>
        </>
    );
}

function displayField(field, value, row) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (field === 'total' || field === 'amount' || field === 'price') {
        return `${row.currency ?? 'USD'} ${Number(value).toFixed(2)}`;
    }
    if (field.endsWith('_at') && typeof value === 'string') return new Date(value).toLocaleString();
    if (field === 'description' && typeof value === 'string') return value.length > 100 ? `${value.slice(0, 100)}…` : value;
    return String(value).replaceAll('_', ' ');
}

function PlannedSection({ title }) {
    return (
        <section className="rounded-xl border border-[var(--rr-border)] bg-white p-8 sm:p-10">
            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">Not implemented yet</p>
            <h2 className="mt-2 text-2xl font-semibold">{title}</h2>
            <p className="mt-3 max-w-2xl text-sm leading-6 text-[var(--rr-neutral-mid)]">This area is not presented as working functionality. It will be enabled only after its backend workflow, authorization, validation, and audit coverage are implemented.</p>
        </section>
    );
}

function PageNotFound({ navigate }) {
    return <section className="rounded-xl border border-[var(--rr-border)] bg-white p-8 text-center"><h2 className="text-xl font-semibold">That provider page is not available.</h2><p className="mt-2 text-sm text-[var(--rr-neutral-mid)]">This route does not correspond to a provider workspace area.</p><button type="button" onClick={() => navigate('overview')} className={`${primaryButton} mt-5`}>Return to overview</button></section>;
}

function Metric({ label, value, note }) {
    return <article className="rounded-xl border border-[var(--rr-border)] bg-white p-5 shadow-sm"><p className="text-xs font-semibold text-[var(--rr-neutral-mid)]">{label}</p><p className="mt-3 text-3xl font-semibold tracking-tight">{value}</p><p className="mt-2 text-xs leading-5 text-[var(--rr-neutral-mid)]">{note}</p></article>;
}

function Panel({ title, eyebrow, className = '', children }) {
    return <section className={`rounded-xl border border-[var(--rr-border)] bg-white p-5 shadow-sm sm:p-6 ${className}`}>{(title || eyebrow) && <div className="mb-5">{eyebrow && <p className="text-[10px] font-bold uppercase tracking-[0.15em] text-[var(--rr-accent)]">{eyebrow}</p>}{title && <h2 className="mt-1 text-lg font-semibold">{title}</h2>}</div>}{children}</section>;
}

function PageTitle({ eyebrow, title, description }) {
    return <div><p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">{eyebrow}</p><h2 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{title}</h2><p className="mt-2 max-w-3xl text-sm leading-6 text-[var(--rr-neutral-mid)]">{description}</p></div>;
}

function Select({ label, value, onChange, options }) {
    return <label className="block min-w-40"><span className="sr-only">{label}</span><select aria-label={label} value={value} onChange={(event) => onChange(event.target.value)} className={inputClass}><option value="">{label}: All</option>{options.map(([option, text]) => <option key={option} value={option} className="capitalize">{text}</option>)}</select></label>;
}

function Pagination({ meta, onPage }) {
    if (meta.last_page <= 1) return null;
    return <div className="mt-5 flex items-center justify-between border-t border-[var(--rr-border)] pt-4"><p className="text-xs text-[var(--rr-neutral-mid)]">Page {meta.current_page} of {meta.last_page} · {meta.total} total</p><div className="flex gap-2"><button type="button" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)} className={secondaryButton}>Previous</button><button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)} className={secondaryButton}>Next</button></div></div>;
}

function Loading() {
    return <div aria-label="Loading service provider data" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div className="h-32 animate-pulse rounded-xl bg-white" /><div className="h-32 animate-pulse rounded-xl bg-white" /><div className="h-32 animate-pulse rounded-xl bg-white" /><div className="h-32 animate-pulse rounded-xl bg-white" /></div>;
}

function ErrorState({ message, onRetry }) {
    return <div role="alert" className="rounded-xl border border-red-200 bg-white p-6"><p className="font-semibold text-[var(--rr-error)]">Admin data is unavailable</p><p className="mt-2 text-sm text-[var(--rr-neutral-mid)]">{message}</p><button type="button" onClick={onRetry} className={`${primaryButton} mt-4`}>Retry</button></div>;
}

function InlineError({ children }) {
    return <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{children}</div>;
}

function Empty({ title, children }) {
    return <div className="rounded-lg border border-dashed border-[var(--rr-border)] bg-[var(--rr-background)] px-5 py-10 text-center"><h3 className="font-semibold">{title}</h3><p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">{children}</p></div>;
}

function date(value) {
    return value ? new Date(value).toLocaleDateString() : '—';
}

function moneyList(entries) {
    return entries.length ? entries.map(({ amount, currency: code }) => currency(amount, code)).join(' · ') : '$0.00';
}

function currency(value, code = 'USD') {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: code }).format(Number(value || 0));
    } catch {
        return `${code} ${Number(value || 0).toFixed(2)}`;
    }
}

const UserType = { regular: 'regular', admin: 'super_admin' };
const inputClass = 'min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 py-2 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20';
const primaryButton = 'min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)] disabled:cursor-not-allowed disabled:opacity-40';
const secondaryButton = 'min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)] disabled:cursor-not-allowed disabled:opacity-40';
const statusClasses = { neutral: 'bg-[var(--rr-neutral-light)] text-[var(--rr-neutral-dark)]', success: 'bg-[var(--rr-accent-light)] text-[var(--rr-accent)]' };

function Status({ children, tone = 'neutral' }) {
    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusClasses[tone]}`}>{children}</span>;
}
