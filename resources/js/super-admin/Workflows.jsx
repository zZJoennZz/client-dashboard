import React, { useEffect, useMemo, useState } from 'react';

const input = 'min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 py-2 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20';
const button = 'min-h-10 rounded-lg bg-[var(--rr-primary)] px-3 text-xs font-semibold text-white hover:bg-[var(--rr-primary-light)] disabled:opacity-50';
const secondary = 'min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)]';

const schemas = {
    content: [
        ['title', 'Title', 'text', true], ['description', 'Description', 'textarea'],
        ['business_id', 'Business ID (optional; see Member businesses)', 'number'], ['resource_type', 'Resource type', 'text', true],
        ['status', 'Status', 'select', true, ['draft', 'published']],
        ['visibility', 'Visibility', 'select', true, ['private', 'member', 'public']],
        ['is_featured', 'Featured', 'checkbox'], ['file', 'Attachment (PDF, office, CSV, image; max 20MB)', 'file'],
    ],
    reports: [
        ['title', 'Title', 'text', true], ['description', 'Description', 'textarea'],
        ['business_id', 'Business ID (see Member businesses)', 'number', true], ['reporting_period', 'Reporting period', 'text'],
        ['status', 'Status', 'select', true, ['draft', 'published']],
        ['visibility', 'Visibility', 'select', true, ['private', 'member', 'public']],
        ['file', 'Report file (required for new report; max 20MB)', 'file'],
    ],
    events: [
        ['title', 'Title', 'text', true], ['business_id', 'Business ID (see Member businesses)', 'number', true],
        ['description', 'Description', 'textarea'], ['location', 'Location', 'text'],
        ['meeting_url', 'Meeting URL', 'url'], ['starts_at', 'Starts at', 'datetime-local', true],
        ['ends_at', 'Ends at', 'datetime-local', true], ['registration_deadline', 'Registration deadline', 'datetime-local'],
        ['capacity', 'Capacity', 'number'], ['registration_enabled', 'Enable RSVP', 'checkbox'],
        ['status', 'Status', 'select', true, ['draft', 'scheduled', 'published', 'cancelled']],
        ['is_published', 'Published', 'checkbox'],
    ],
    marketing: [
        ['title', 'Activity title', 'text', true], ['business_id', 'Business ID (see Member businesses)', 'number', true],
        ['promotion_type', 'Promotion type', 'text', true], ['platform', 'Platform', 'text'],
        ['scheduled_at', 'Scheduled at', 'datetime-local'], ['status', 'Status', 'select', true, ['planned', 'scheduled', 'completed', 'cancelled']],
        ['responsible_admin_id', 'Assigned provider staff ID', 'number'], ['caption', 'Content / caption', 'textarea'],
        ['description', 'Internal notes', 'textarea'], ['member_description', 'Member-facing activity notes', 'textarea'],
        ['published_url', 'Published URL (manual posting only)', 'url'], ['completed_at', 'Completed at', 'datetime-local'],
    ],
    services: [
        ['name', 'Service name', 'text', true], ['description', 'Description', 'textarea'],
        ['price', 'Catalog price', 'number', true], ['currency', 'Currency code', 'text', true],
        ['status', 'Status', 'select', true, ['available', 'unavailable']],
        ['is_featured', 'Featured', 'checkbox'], ['sort_order', 'Sort order', 'number'],
    ],
};

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function showMembershipDetails(row) {
    try {
        setMembershipDetails(await jsonRequest(`${urls.memberships}/${row.id}`));
    } catch (exception) { setError(exception.message); }
}

async function jsonRequest(url, options = {}) {
    const formData = options.body instanceof FormData;
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.body ? { 'X-CSRF-TOKEN': csrf() } : {}),
            ...(!formData && options.body ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
        },
    });
    if (response.status === 401 || response.redirected) {
        window.location.assign(document.getElementById('super-admin')?.dataset.loginUrl ?? '/login');
        throw new Error('Your session has expired. Please sign in again.');
    }
    const payload = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = Object.values(payload.errors ?? {}).flat();
        throw new Error(errors.join(' ') || payload.message || `The request failed (HTTP ${response.status}).`);
    }
    return payload;
}

export default function AdminWorkflows({ section, workspaceUrl, urls, exportUrl }) {
    const membership = section === 'memberships';
    const contentSection = section === 'content' || section === 'reports';
    const isEvent = section === 'events';
    const marketing = section === 'marketing';
    const orders = section === 'orders';
    const services = section === 'services';
    const communications = section === 'communications';
    const [filters, setFilters] = useState({ page: 1, search: '', status: '', business_id: '' });
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [record, setRecord] = useState({});
    const [editingId, setEditingId] = useState(null);
    const [preview, setPreview] = useState(null);
    const [registrations, setRegistrations] = useState(null);
    const [membershipDetails, setMembershipDetails] = useState(null);
    const [calendarMode, setCalendarMode] = useState(false);
    const [query, setQuery] = useState('');

    const listingSection = services ? 'services' : section;
    const listUrl = communications ? urls.announcements : `${workspaceUrl}/${listingSection}`;
    const params = useMemo(() => new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '' && value !== null)), [filters]);

    async function refresh() {
        setLoading(true);
        setError('');
        try {
            const payload = await jsonRequest(`${listUrl}?${params}`);
            setData(payload.data?.data ? payload.data : payload);
        } catch (exception) {
            setError(exception.message);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => { refresh(); }, [listUrl, params.toString()]);

    const schema = schemas[section] ?? [];
    const title = {
        memberships: 'Membership adjustments', content: 'Member resources', reports: 'Business reports',
        events: 'Event management', marketing: 'Marketing activities', orders: 'Order fulfillment',
        services: 'Premium service catalog', communications: 'Member announcements',
    }[section] ?? section;

    function setField(key, value) {
        setRecord((current) => ({ ...current, [key]: value }));
    }

    function startEdit(row) {
        setEditingId(row.id);
        const values = {};
        for (const [key, , type] of schema) {
            const value = row[key] ?? '';
            values[key] = type === 'datetime-local' && value ? String(value).slice(0, 16).replace(' ', 'T') : value;
        }
        if (schema.some(([key]) => key === 'registration_enabled')) values.registration_enabled = Boolean(row.registration_enabled);
        if (schema.some(([key]) => key === 'is_published')) values.is_published = Boolean(row.is_published);
        if (schema.some(([key]) => key === 'is_featured')) values.is_featured = Boolean(row.is_featured ?? row.featured);
        setRecord(values);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function submit(event) {
        event.preventDefault();
        const formElement = event.currentTarget;
        setNotice('');
        setError('');
        const form = new FormData(formElement);
        let payload;
        if (contentSection) {
            payload = new FormData();
            for (const [key, value] of form.entries()) payload.append(key, value);
            for (const [key, , type] of schema) {
                if (type === 'checkbox') payload.set(key, form.has(key) ? '1' : '0');
            }
            payload.set('status', form.get('status') || 'draft');
            payload.set('visibility', form.get('visibility') || 'private');
            if (section === 'content' && !form.has('is_featured')) payload.set('is_featured', '0');
            if (editingId) payload.append('_method', 'PUT');
        } else {
            payload = Object.fromEntries(form.entries());
            for (const [key, , type] of schema) {
                if (type === 'checkbox') payload[key] = form.has(key) ? '1' : '0';
                if (type === 'number' && payload[key] === '') payload[key] = null;
                if (type === 'datetime-local' && payload[key]) payload[key] = new Date(payload[key]).toISOString();
            }
        }
        let url;
        let method;
        if (contentSection) {
            url = editingId ? `${urls[section]}/${editingId}` : urls[section];
            method = 'POST';
        } else if (isEvent) {
            url = editingId ? `${urls.events}/${editingId}` : urls.events;
            method = editingId ? 'PUT' : 'POST';
        } else if (marketing) {
            url = editingId ? `${urls.marketing}/${editingId}` : urls.marketing;
            method = editingId ? 'PUT' : 'POST';
        } else if (services) {
            url = editingId ? `${urls.services}/${editingId}` : urls.services;
            method = editingId ? 'PUT' : 'POST';
        } else {
            url = editingId ? `${urls.announcements}/${editingId}` : urls.announcements;
            method = editingId ? 'PUT' : 'POST';
            payload.audience = { business_category: payload.business_category || null, membership_status: payload.membership_status || null };
            delete payload.business_category;
            delete payload.membership_status;
        }
        try {
            const body = payload instanceof FormData ? payload : JSON.stringify(payload);
            const result = await jsonRequest(url, { method, body });
            setNotice(editingId ? 'Changes saved.' : 'Record created.');
            setRecord({});
            setEditingId(null);
            formElement.reset();
            await refresh();
        } catch (exception) {
            setError(exception.message);
        }
    }

    async function action(url, method = 'PATCH', body = {}) {
        setError('');
        setNotice('');
        try {
            await jsonRequest(url, { method, body: JSON.stringify(body) });
            setNotice('Update completed successfully.');
            await refresh();
        } catch (exception) {
            setError(exception.message);
        }
    }

    async function exportCsv(type, extra = {}) {
        try {
            const exportFilters = Object.fromEntries(Object.entries(filters).filter(([key]) => key !== 'page'));
            const response = await fetch(`${exportUrl}/${type}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'text/csv', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ ...exportFilters, ...extra }),
            });
            if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message || 'CSV export failed.');
            const blob = await response.blob();
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/)?.[1] || `${type}.csv`;
            link.click();
            URL.revokeObjectURL(link.href);
        } catch (exception) {
            setError(exception.message);
        }
    }

    async function previewRecipients() {
        try {
            const form = new FormData(document.querySelector('#announcement-form'));
            const result = await jsonRequest(`${urls.announcements}/preview`, {
                method: 'POST',
                body: JSON.stringify({ business_category: form.get('business_category') || null, membership_status: form.get('membership_status') || null }),
            });
            setPreview(result);
        } catch (exception) {
            setError(exception.message);
        }
    }

    async function showRegistrants(row) {
        try {
            const result = await jsonRequest(`${urls.events}/${row.id}/registrations`);
            setRegistrations(result);
        } catch (exception) { setError(exception.message); }
    }

    async function updateRegistrant(person, status) {
        try {
            await jsonRequest(`${urls.eventRegistrations}/${person.id}`, {
                method: 'PATCH',
                body: JSON.stringify({ status }),
            });
            setRegistrations((current) => ({
                ...current,
                data: {
                    ...current.data,
                    data: current.data.data.map((item) => item.id === person.id ? { ...item, status } : item),
                },
            }));
            setNotice('Registrant status updated.');
        } catch (exception) {
            setError(exception.message);
        }
    }

    async function updateMembership(row, formData) {
        const body = Object.fromEntries(formData.entries());
        if (!body.status) delete body.status;
        if (!body.end_date) delete body.end_date;
        if (!body.cancellation_reason) delete body.cancellation_reason;
        await action(`${urls.memberships}/${row.id}`, 'PATCH', body);
    }

    if (loading && !data) return <Panel title={title}>Loading records…</Panel>;

    return (
        <div className="space-y-5">
            <header>
                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">Service provider workflow</p>
                <h2 className="mt-2 text-2xl font-semibold">{title}</h2>
                <p className="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{explanation(section)}</p>
            </header>
            {error && <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</div>}
            {notice && <div role="status" className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">{notice}</div>}

            {(schema.length > 0 || communications) && <Panel title={editingId ? 'Edit record' : 'Create new record'}>
                <form key={editingId || 'new'} id={communications ? 'announcement-form' : undefined} onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                    {schema.map(([key, label, type, required, options]) => <Field key={key} name={key} label={label} type={type} required={required} options={options} value={record[key]} onChange={(value) => setField(key, value)} />)}
                    {communications && <>
                        <Field name="subject" label="Subject" required value={record.subject} />
                        <Field name="body" label="Message" type="textarea" required value={record.body} />
                        <Field name="business_category" label="Business category filter (optional)" value={record.audience?.business_category} />
                        <Field name="membership_status" label="Membership status filter (optional)" value={record.audience?.membership_status} />
                    </>}
                    {schema.length > 0 && <div className="flex items-end gap-2 sm:col-span-2"><button className={button} type="submit">{editingId ? 'Save changes' : 'Create'}</button>{editingId && <button type="button" className={secondary} onClick={() => { setEditingId(null); setRecord({}); }}>Cancel edit</button>}</div>}
                    {communications && <div className="flex flex-wrap items-center gap-2 sm:col-span-2"><button className={button} type="submit">{editingId ? 'Save draft' : 'Create draft'}</button><button className={secondary} type="button" onClick={previewRecipients}>Preview recipient count</button>{preview && <span className="text-sm">{preview.recipient_count} eligible recipients. Only opted-in members are included.</span>}</div>}
                </form>
            </Panel>}

            <Panel title={`${data?.meta?.total ?? data?.data?.length ?? 0} records`}>
                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <label className="min-w-48 flex-1 text-xs font-semibold">Search
                        <input className={`${input} mt-1`} value={query} onChange={(event) => setQuery(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') setFilters((current) => ({ ...current, search: query, page: 1 })); }} />
                    </label>
                    <label className="text-xs font-semibold">Status
                        <select className={`${input} mt-1`} value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value, page: 1 }))}>
                            <option value="">All statuses</option>{(section === 'memberships' ? data?.status_options ?? [] : statusesFor(section)).map((status) => <option key={status} value={status}>{status}</option>)}
                        </select>
                    </label>
                    {orders && <label className="text-xs font-semibold">Payment status
                        <select className={`${input} mt-1`} value={filters.payment_status || ''} onChange={(event) => setFilters((current) => ({ ...current, payment_status: event.target.value, page: 1 }))}>
                            <option value="">All payment states</option>{['unpaid', 'partially_paid', 'paid', 'refunded', 'waived'].map((status) => <option key={status}>{status}</option>)}
                        </select>
                    </label>}
                    <label className="text-xs font-semibold">Business ID
                        <input className={`${input} mt-1 w-36`} value={filters.business_id} onChange={(event) => setFilters((current) => ({ ...current, business_id: event.target.value, page: 1 }))} />
                    </label>
                    <label className="text-xs font-semibold">From<input type="date" className={`${input} mt-1`} value={filters.from || ''} onChange={(event) => setFilters((current) => ({ ...current, from: event.target.value, page: 1 }))} /></label>
                    <label className="text-xs font-semibold">To<input type="date" className={`${input} mt-1`} value={filters.to || ''} onChange={(event) => setFilters((current) => ({ ...current, to: event.target.value, page: 1 }))} /></label>
                    <button type="button" className={secondary} onClick={() => setFilters((current) => ({ ...current, search: query, page: 1 }))}>Apply filters</button>
                    {marketing && <button type="button" className={secondary} onClick={() => setCalendarMode((current) => !current)}>{calendarMode ? 'List view' : 'Calendar view'}</button>}
                    {exportType(section) && <button type="button" className={secondary} onClick={() => exportCsv(exportType(section))}>Export CSV</button>}
                </div>
                {marketing && calendarMode && dataRows(data).length > 0 ? <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {Object.entries(dataRows(data).reduce((groups, row) => {
                        const day = row.scheduled_at ? new Date(row.scheduled_at).toLocaleDateString() : 'Unscheduled';
                        groups[day] = [...(groups[day] ?? []), row];
                        return groups;
                    }, {})).map(([day, rows]) => <section key={day} className="rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] p-3">
                        <h3 className="mb-2 text-sm font-semibold">{day}</h3>{rows.map((row) => <p key={row.id} className="border-t border-[var(--rr-border)] py-2 text-xs">{row.title} · {row.status}</p>)}
                    </section>)}
                </div> : dataRows(data).length === 0 ? <p className="rounded-lg bg-[var(--rr-background)] p-6 text-sm text-[var(--rr-neutral-mid)]">No records match these filters.</p> : <div className="space-y-3">
                    {dataRows(data).map((row) => <article key={row.id} className="rounded-lg border border-[var(--rr-border)] p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div><h3 className="font-semibold">{row.title || row.name || row.subject || row.order_number || row.business_name || `Record #${row.id}`}</h3><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{row.business_name || row.plan || row.service_name || ''} {row.status ? ` · ${row.status}` : ''}{row.deleted ? ' · Archived' : ''}</p><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{row.file_name || row.scheduled_at || row.starts_at || row.end_date || ''}</p>{communications && <p className="mt-2 text-xs text-[var(--rr-neutral-mid)]">Recipients: {row.recipient_count} · Delivered: {row.delivered_count} · Failed: {row.failed_count}{row.failed_recipients?.length ? ` (${row.failed_recipients.join(', ')})` : ''}</p>}</div>
                            <Actions section={section} row={row} urls={urls} action={action} startEdit={startEdit} showMembershipDetails={showMembershipDetails} startAnnouncementEdit={(item) => {
                                setEditingId(item.id);
                                setRecord({ subject: item.subject, body: item.body, audience: item.audience ?? {} });
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            }} showRegistrants={showRegistrants} exportCsv={exportCsv} />
                        </div>
                        {membership && <form onSubmit={(event) => { event.preventDefault(); updateMembership(row, new FormData(event.currentTarget)); }} className="mt-4 grid gap-2 sm:grid-cols-4">
                            <label className="text-xs">Renewal date<input name="end_date" type="date" defaultValue={row.end_date || ''} className={`${input} mt-1`} /></label>
                            <label className="text-xs">Status<select name="status" defaultValue="" className={`${input} mt-1`}><option value="">Keep current</option>{(data.status_options ?? []).map((status) => <option key={status}>{status}</option>)}</select></label>
                            <label className="text-xs">Cancellation reason<input name="cancellation_reason" className={`${input} mt-1`} /></label>
                            <button className={`${secondary} self-end`} type="submit">Save adjustment</button>
                            <MembershipExtension row={row} url={urls.memberships} action={action} />
                            {row.payment_is_simulated && <p className="text-xs text-amber-700 sm:col-span-4">Simulated registration payment placeholder — not a verified financial transaction.</p>}
                        </form>}
                        {orders && <OrderEditor row={row} url={urls.orders} action={action} />}
                    </article>)}
                </div>}
                {data?.meta?.last_page > 1 && <div className="mt-4 flex justify-between"><button className={secondary} disabled={filters.page <= 1} onClick={() => setFilters((current) => ({ ...current, page: current.page - 1 }))}>Previous</button><span className="self-center text-xs">Page {filters.page} of {data.meta.last_page}</span><button className={secondary} disabled={filters.page >= data.meta.last_page} onClick={() => setFilters((current) => ({ ...current, page: current.page + 1 }))}>Next</button></div>}
            </Panel>

            {membershipDetails && <Panel title={`Membership history — ${membershipDetails.business || 'Business'}`}>
                <p className="text-sm">{membershipDetails.plan || 'No plan'} · {membershipDetails.status} · Renewal {membershipDetails.renewal_date || 'not set'}</p>
                {membershipDetails.history?.length ? <ul className="mt-3 divide-y divide-[var(--rr-border)]">{membershipDetails.history.map((item, index) => <li key={`${item.action}-${index}`} className="py-2 text-sm"><strong>{item.action}</strong> · {item.actor} · {new Date(item.created_at).toLocaleString()}</li>)}</ul> : <p className="mt-2 text-xs text-[var(--rr-neutral-mid)]">No recorded membership adjustment history.</p>}
            </Panel>}

            {registrations && <Panel title={`Registrants — ${registrations.event.title}`}><button className={secondary} onClick={() => exportCsv('registrants', { event_id: registrations.event.id })}>Export registrants CSV</button><div className="mt-3 divide-y">{registrations.data.data.map((person) => <div key={person.id} className="flex flex-wrap justify-between gap-3 py-3"><div><p className="font-medium">{person.name}</p><p className="text-xs text-[var(--rr-neutral-mid)]">{person.email} · {person.status}</p></div><select className={input} value={person.status} onChange={(event) => updateRegistrant(person, event.target.value)}>{['registered', 'confirmed', 'attended', 'cancelled'].map((status) => <option key={status}>{status}</option>)}</select></div>)}</div></Panel>}
        </div>
    );
}

function Field({ name, label, type = 'text', required = false, options = [], value, onChange }) {
    const common = { name, required, className: `${input} mt-1`, defaultValue: value ?? '' };
    return <label className={`block text-xs font-semibold ${type === 'textarea' ? 'sm:col-span-2' : ''}`}>{label}
        {type === 'textarea' ? <textarea {...common} rows="3" /> :
            type === 'select' ? <select {...common}>{options.map((option) => <option key={option}>{option}</option>)}</select> :
                type === 'checkbox' ? <input name={name} type="checkbox" defaultChecked={value === undefined ? name === 'registration_enabled' : Boolean(value)} className="ml-2 min-h-5 min-w-5 align-middle accent-[var(--rr-primary)]" /> :
                    <input {...common} type={type} onChange={onChange ? (event) => onChange(event.target.value) : undefined} />}
    </label>;
}

function Actions({ section, row, urls, action, startEdit, startAnnouncementEdit, showRegistrants, showMembershipDetails, exportCsv }) {
    return <div className="flex flex-wrap gap-2">
        {['content', 'reports', 'events', 'marketing'].includes(section) && <button className={secondary} onClick={() => startEdit(row)}>Edit</button>}
        {section === 'services' && !row.deleted && <button className={secondary} onClick={() => startEdit(row)}>Edit</button>}
        {section === 'content' && <>{row.file_name && <a className={`${secondary} inline-flex items-center`} href={`${urls.resources}/${row.id}/download`}>Download</a>}<button className={secondary} onClick={() => action(`${urls.resources}/${row.id}`, 'DELETE')}>Archive</button></>}
        {section === 'reports' && <>{row.file_name && <a className={`${secondary} inline-flex items-center`} href={`${urls.reports}/${row.id}/download`}>Download</a>}<button className={secondary} onClick={() => action(`${urls.reports}/${row.id}`, 'DELETE')}>Archive</button></>}
        {section === 'events' && <><button className={secondary} onClick={() => action(`${urls.events}/${row.id}/status`, 'PATCH', { status: row.status === 'published' ? 'draft' : 'published' })}>{row.status === 'published' ? 'Unpublish' : 'Publish'}</button><button className={secondary} onClick={() => action(`${urls.events}/${row.id}/status`, 'PATCH', { status: 'cancelled' })}>Cancel</button><button className={secondary} onClick={() => showRegistrants(row)}>Registrants</button></>}
        {section === 'marketing' && <p className="w-full text-xs text-[var(--rr-neutral-mid)]">Posting is manual; no social network publishing is connected.</p>}
        {section === 'services' && null}
        {section === 'communications' && <>{row.status === 'draft' && <button className={secondary} onClick={() => startAnnouncementEdit(row)}>Edit draft</button>}{['draft', 'failed'].includes(row.status) && <button className={secondary} onClick={() => window.confirm('Send this announcement to eligible, opted-in members? This cannot be undone.') && action(`${urls.announcements}/${row.id}/send`, 'POST')}>{row.status === 'failed' ? 'Retry failed deliveries' : 'Approve and send'}</button>}</>}
        {section === 'memberships' && <button className={secondary} onClick={() => exportCsv('memberships', { business_id: row.business_id })}>Export memberships</button>}
        {section === 'memberships' && <button className={secondary} onClick={() => showMembershipDetails(row)}>History</button>}
        {section === 'orders' && <button className={secondary} onClick={() => exportCsv('orders', { business_id: row.business_id })}>Export orders</button>}
    </div>;
}

function MembershipExtension({ row, url, action }) {
    const [days, setDays] = useState(30);
    return <div className="flex items-end gap-2 sm:col-span-4"><label className="text-xs">Complimentary days<input className={`${input} mt-1 w-36`} type="number" min="1" max="3650" value={days} onChange={(event) => setDays(event.target.value)} /></label><button type="button" className={secondary} onClick={() => action(`${url}/${row.id}/complimentary-extension`, 'POST', { days: Number(days) })}>Grant complimentary extension</button></div>;
}

function OrderEditor({ row, url, action }) {
    return <form className="mt-4 grid gap-2 sm:grid-cols-4" onSubmit={(event) => { event.preventDefault(); const values = Object.fromEntries(new FormData(event.currentTarget)); action(`${url}/${row.id}/fulfillment`, 'PATCH', values); }}>
        <label className="text-xs">Fulfillment status<select name="status" defaultValue={row.status} className={`${input} mt-1`}>{['draft', 'submitted', 'under_review', 'approved', 'in_progress', 'completed', 'cancelled'].map((status) => <option key={status}>{status}</option>)}</select></label>
        <label className="text-xs">Assigned provider staff ID (see Members and accounts)<input name="assigned_staff_id" type="number" defaultValue={row.assigned_staff_id || ''} className={`${input} mt-1`} /></label>
        <label className="text-xs">Fulfillment notes<input name="fulfillment_notes" defaultValue={row.fulfillment_notes || ''} className={`${input} mt-1`} /></label>
        <button className={`${secondary} self-end`}>Save fulfillment</button>
    </form>;
}

function statusesFor(section) {
    return {
        memberships: ['active', 'pending_activation', 'delinquent', 'cancelled', 'expired'],
        content: ['draft', 'published'], reports: ['draft', 'published'],
        events: ['draft', 'scheduled', 'published', 'cancelled'],
        marketing: ['planned', 'scheduled', 'completed', 'cancelled'],
        orders: ['pending', 'processing', 'completed', 'cancelled', 'refunded'],
        services: ['available', 'unavailable'], communications: ['draft', 'sent', 'failed'],
    }[section] ?? [];
}

function dataRows(payload) {
    if (Array.isArray(payload?.data)) return payload.data;
    if (payload?.data?.data) return payload.data.data;
    return [];
}

function exportType(section) {
    return { memberships: 'memberships', events: 'registrants', marketing: 'marketing', orders: 'orders' }[section] ?? null;
}

function explanation(section) {
    return {
        memberships: 'Adjust renewal dates and grant audited complimentary extensions. Simulated paid registration placeholders are not real transactions.',
        content: 'Create, edit, publish, and archive general member resources. Files stay in private storage and download routes are authorization checked.',
        reports: 'Upload business reports and control publication. Members can only download published reports for an associated business.',
        events: 'Manage event details, publication, cancellation, capacity, RSVP, registration lists, and CSV export.',
        marketing: 'Schedule and track manually executed promotions. This tool does not publish automatically to social media.',
        orders: 'Update fulfillment status, staff assignment, and notes while preserving historical item prices.',
        services: 'Maintain the premium services catalog. Deactivation preserves historical orders.',
        communications: 'Draft announcements, preview eligible opted-in member counts, and send only when a delivery transport is configured.',
    }[section] ?? '';
}

function Panel({ title, children }) {
    return <section className="rounded-xl border border-[var(--rr-border)] bg-white p-5 shadow-sm sm:p-6"><h2 className="mb-4 text-lg font-semibold">{title}</h2>{children}</section>;
}
