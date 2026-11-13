import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Card, EmptyState, PageHeading, Status } from '../components/UI';

export default function Marketing({ data }) {
    const [type, setType] = useState('all');
    const [month, setMonth] = useState('');
    const [selectedPromotion, setSelectedPromotion] = useState(null);
    const modalTitleRef = useRef(null);
    const triggerRef = useRef(null);
    const activities = data.marketing_activities ?? [];
    const types = data.marketing_options?.promotion_types ?? [];
    const filtered = activities.filter((item) => {
        const date = item.scheduled_at ? new Date(item.scheduled_at) : null;
        return (type === 'all' || item.promotion_type === type)
            && (!month || (date && `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}` === month));
    });
    const now = Date.now();
    const nextThirtyDays = now + (30 * 24 * 60 * 60 * 1000);
    const upcoming = filtered.filter((item) => {
        const scheduledAt = item.scheduled_at ? new Date(item.scheduled_at).getTime() : NaN;
        return scheduledAt >= now && scheduledAt <= nextThirtyDays && item.status !== 'cancelled';
    });

    useEffect(() => {
        if (!selectedPromotion) return undefined;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        modalTitleRef.current?.focus();

        function handleEscape(event) {
            if (event.key === 'Escape') setSelectedPromotion(null);
        }

        document.addEventListener('keydown', handleEscape);

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', handleEscape);
            triggerRef.current?.focus();
        };
    }, [selectedPromotion]);

    function openPromotion(item, event) {
        triggerRef.current = event.currentTarget;
        setSelectedPromotion(item);
    }

    return (
        <div className="space-y-5">
            <PageHeading eyebrow="Member resources" title="Marketing and promotions">
                View upcoming and published platform-wide promotions.
            </PageHeading>
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)]">
                <Card title="Promotion calendar">
                    <div className="mb-4 flex flex-wrap gap-3">
                        <label className="text-xs font-semibold">Promotion type<select className="mt-1 block min-h-10 rounded-lg border border-[var(--rr-border)] bg-white px-3 text-sm" value={type} onChange={(event) => setType(event.target.value)}><option value="all">All types</option>{types.map((item) => <option key={item}>{item}</option>)}</select></label>
                        <label className="text-xs font-semibold">Month<input className="mt-1 block min-h-10 rounded-lg border border-[var(--rr-border)] bg-white px-3 text-sm" type="month" value={month} onChange={(event) => setMonth(event.target.value)} /></label>
                        {month && <button className="mt-4 min-h-10 px-2 text-xs font-semibold text-[var(--rr-primary)] underline" onClick={() => setMonth('')}>Clear month</button>}
                    </div>
                    {filtered.length === 0 ? <EmptyState title="No promotions scheduled">Try another month or promotion type.</EmptyState> : <ScheduleCalendar activities={filtered} onSelect={openPromotion} />}
                </Card>
                <Card title="Upcoming">
                    {upcoming.length === 0
                        ? <p className="text-sm text-[var(--rr-neutral-mid)]">No promotions scheduled in the next 30 days.</p>
                        : <ol className="divide-y divide-[var(--rr-border)]">{upcoming.map((item) => <li key={item.id}><Activity item={item} onSelect={openPromotion} /></li>)}</ol>}
                </Card>
            </div>
            {/* <Card title="All matching promotions">
                {filtered.length === 0 ? <EmptyState title="No matching promotions">Scheduled and completed promotions will appear here.</EmptyState> : <div className="divide-y divide-[var(--rr-border)]">{filtered.map((item) => <Activity key={item.id} item={item} onSelect={openPromotion} />)}</div>}
            </Card> */}
            {selectedPromotion && createPortal(
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="promotion-details-title"
                    className="dashboard-modal-backdrop fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/55 p-0 sm:items-center sm:p-5"
                    onClick={() => setSelectedPromotion(null)}
                >
                    <section className="dashboard-modal-panel max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-white p-5 shadow-2xl sm:rounded-2xl sm:p-7" onClick={(event) => event.stopPropagation()}>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--rr-accent)]">Promotion details</p>
                                <h2 id="promotion-details-title" ref={modalTitleRef} tabIndex={-1} className="mt-1 text-xl font-semibold">{selectedPromotion.title}</h2>
                            </div>
                            <button type="button" aria-label="Close promotion details" onClick={() => setSelectedPromotion(null)} className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-[var(--rr-border)] text-lg hover:bg-[var(--rr-background)]">×</button>
                        </div>
                        <div className="mt-5 flex flex-wrap items-center gap-2">
                            <span className="rounded-full bg-[var(--rr-background)] px-3 py-1 text-xs font-medium">{selectedPromotion.promotion_type}</span>
                            <Status tone={selectedPromotion.status === 'completed' ? 'success' : 'neutral'}>{selectedPromotion.status}</Status>
                        </div>
                        <dl className="mt-5 grid gap-4 rounded-xl border border-[var(--rr-border)] bg-[var(--rr-background)] p-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs font-semibold text-[var(--rr-neutral-mid)]">Scheduled</dt>
                                <dd className="mt-1 text-sm">{selectedPromotion.scheduled_at ? new Date(selectedPromotion.scheduled_at).toLocaleString() : 'Date to be confirmed'}</dd>
                            </div>
                            {selectedPromotion.completed_at && <div>
                                <dt className="text-xs font-semibold text-[var(--rr-neutral-mid)]">Completed</dt>
                                <dd className="mt-1 text-sm">{new Date(selectedPromotion.completed_at).toLocaleString()}</dd>
                            </div>}
                        </dl>
                        {selectedPromotion.member_description && <p className="mt-5 whitespace-pre-wrap text-sm leading-6">{selectedPromotion.member_description}</p>}
                        <div className="mt-5">
                            <h3 className="text-sm font-semibold">Platforms and posts</h3>
                            {selectedPromotion.platforms?.length
                                ? <ul className="mt-2 flex flex-wrap gap-2">{selectedPromotion.platforms.map((platform) => <li key={platform.platform}>{platform.url
                                    ? <a className="inline-flex min-h-10 items-center rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold text-[var(--rr-primary)] underline" href={platform.url} target="_blank" rel="noreferrer">{platform.platform} post</a>
                                    : <span className="inline-flex min-h-10 items-center rounded-lg bg-[var(--rr-background)] px-3 text-xs text-[var(--rr-neutral-mid)]">{platform.platform}</span>}</li>)}</ul>
                                : <p className="mt-2 text-sm text-[var(--rr-neutral-mid)]">No platforms listed.</p>}
                        </div>
                    </section>
                </div>,
                document.body,
            )}
        </div>
    );
}

function ScheduleCalendar({ activities, onSelect }) {
    const [viewMonth, setViewMonth] = useState(() => new Date(new Date().getFullYear(), new Date().getMonth(), 1));
    const calendar = useMemo(() => {
        const start = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), 1);
        const first = new Date(start.getFullYear(), start.getMonth(), 1 - start.getDay());
        return Array.from({ length: 42 }, (_, index) => {
            const date = new Date(first.getFullYear(), first.getMonth(), first.getDate() + index);
            return { date, items: activities.filter((item) => item.scheduled_at && new Date(item.scheduled_at).toDateString() === date.toDateString()) };
        });
    }, [activities, viewMonth]);

    return <div>
        <div className="mb-3 flex items-center justify-between"><button aria-label="Previous month" className="min-h-10 min-w-10 rounded-lg border border-[var(--rr-border)]" onClick={() => setViewMonth(new Date(viewMonth.getFullYear(), viewMonth.getMonth() - 1, 1))}>‹</button><h3 className="text-sm font-semibold">{viewMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}</h3><button aria-label="Next month" className="min-h-10 min-w-10 rounded-lg border border-[var(--rr-border)]" onClick={() => setViewMonth(new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 1))}>›</button></div>
        <div className="grid grid-cols-7 border-l border-t border-[var(--rr-border)] text-center text-[10px] font-semibold text-[var(--rr-neutral-mid)]">{['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => <div key={day} className="border-b border-r border-[var(--rr-border)] py-2">{day}</div>)}</div>
        <div className="grid grid-cols-7 border-l border-[var(--rr-border)]">{calendar.map(({ date, items }) => <div key={date.toISOString()} className={`min-h-20 border-b border-r border-[var(--rr-border)] p-1.5 ${date.getMonth() === viewMonth.getMonth() ? 'bg-white' : 'bg-[var(--rr-background)]'}`}><span className={`text-xs ${date.getMonth() === viewMonth.getMonth() ? '' : 'text-[var(--rr-neutral-mid)]'}`}>{date.getDate()}</span><div className="mt-1 space-y-1">{items.slice(0, 2).map((item) => <button key={item.id} type="button" title={item.title} aria-label={`Open promotion: ${item.title}`} onClick={(event) => onSelect(item, event)} className="block w-full truncate rounded bg-[var(--rr-accent-light)] px-1 py-0.5 text-left text-[9px] font-medium text-[var(--rr-primary)] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">{item.title}</button>)}{items.length > 2 && <span className="text-[9px]">+{items.length - 2} more</span>}</div></div>)}</div>
    </div>;
}

function Activity({ item, onSelect }) {
    const platformNames = (item.platforms ?? []).map((platform) => platform.platform).join(' · ');
    return <button type="button" onClick={(event) => onSelect(item, event)} aria-label={`Open promotion details: ${item.title}`} className="flex w-full flex-col gap-2 py-4 text-left first:pt-0 hover:bg-[var(--rr-background)] sm:flex-row sm:items-start sm:justify-between">
        <span>
            <span className="block font-semibold">{item.title}</span>
            <span className="mt-1 block text-sm text-[var(--rr-neutral-mid)]">{item.promotion_type}{platformNames ? ` · ${platformNames}` : ''}</span>
            <span className="mt-1 block text-xs text-[var(--rr-neutral-mid)]">{item.scheduled_at ? new Date(item.scheduled_at).toLocaleString() : 'Date to be confirmed'}</span>
            {item.member_description && <span className="mt-2 line-clamp-2 block text-sm leading-6 text-[var(--rr-neutral-mid)]">{item.member_description}</span>}
        </span>
        <span className="shrink-0"><Status tone={item.status === 'completed' ? 'success' : item.status === 'cancelled' ? 'warning' : 'neutral'}>{item.status}</Status></span>
    </button>;
}
