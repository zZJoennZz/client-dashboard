import React, { useMemo, useState } from 'react';
import { Card, EmptyState, PageHeading, Status } from '../components/UI';

const tabs = ['My Highlights', 'All Program Activities'];

export default function Marketing({ data }) {
    const [activeTab, setActiveTab] = useState(tabs[0]);
    const [type, setType] = useState('all');
    const [month, setMonth] = useState('');
    const activities = activeTab === tabs[0] ? (data.highlights ?? []) : (data.program_activities ?? []);
    const types = [...new Set(activities.map((item) => item.promotion_type).filter(Boolean))].sort();
    const filtered = activities.filter((item) => {
        const date = item.scheduled_at ? new Date(item.scheduled_at) : null;
        return (type === 'all' || item.promotion_type === type)
            && (!month || (date && `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}` === month));
    });

    return (
        <div className="space-y-5">
            <PageHeading eyebrow="Business growth" title="Marketing schedule">
                See when your business is featured and what the program is planning for members.
            </PageHeading>
            <div className="flex flex-wrap gap-2 border-b border-[var(--rr-border)]" role="tablist" aria-label="Marketing schedule">
                {tabs.map((tab) => <button key={tab} role="tab" aria-selected={activeTab === tab} onClick={() => setActiveTab(tab)} className={`min-h-11 border-b-2 px-3 text-sm font-semibold ${activeTab === tab ? 'border-[var(--rr-accent)] text-[var(--rr-primary)]' : 'border-transparent text-[var(--rr-neutral-mid)]'}`}>{tab}</button>)}
            </div>
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)]">
                <Card title={activeTab === tabs[0] ? 'Schedule calendar' : 'Program timeline'}>
                    <div className="mb-4 flex flex-wrap gap-3">
                        <label className="text-xs font-semibold">Activity type<select className="mt-1 block min-h-10 rounded-lg border border-[var(--rr-border)] bg-white px-3 text-sm" value={type} onChange={(event) => setType(event.target.value)}><option value="all">All types</option>{types.map((item) => <option key={item}>{item}</option>)}</select></label>
                        <label className="text-xs font-semibold">Month<input className="mt-1 block min-h-10 rounded-lg border border-[var(--rr-border)] bg-white px-3 text-sm" type="month" value={month} onChange={(event) => setMonth(event.target.value)} /></label>
                        {month && <button className="mt-4 min-h-10 px-2 text-xs font-semibold text-[var(--rr-primary)] underline" onClick={() => setMonth('')}>Clear month</button>}
                    </div>
                    {filtered.length === 0 ? <EmptyState title="No activities scheduled">Try another month or activity type.</EmptyState> : <ScheduleCalendar activities={filtered} />}
                </Card>
                <Card title="Upcoming">
                    {filtered.filter((item) => item.scheduled_at && new Date(item.scheduled_at) >= new Date() && item.status !== 'cancelled').length === 0
                        ? <p className="text-sm text-[var(--rr-neutral-mid)]">No upcoming activities in this view.</p>
                        : <ol className="divide-y divide-[var(--rr-border)]">{filtered.filter((item) => item.scheduled_at && new Date(item.scheduled_at) >= new Date() && item.status !== 'cancelled').map((item) => <Activity key={item.id} item={item} />)}</ol>}
                </Card>
            </div>
            <Card title="All matching activities">
                {filtered.length === 0 ? <EmptyState title="No matching activities">Scheduled and completed activities will appear here.</EmptyState> : <div className="divide-y divide-[var(--rr-border)]">{filtered.map((item) => <Activity key={item.id} item={item} />)}</div>}
            </Card>
        </div>
    );
}

function ScheduleCalendar({ activities }) {
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
        <div className="grid grid-cols-7 border-l border-[var(--rr-border)]">{calendar.map(({ date, items }) => <div key={date.toISOString()} className={`min-h-20 border-b border-r border-[var(--rr-border)] p-1.5 ${date.getMonth() === viewMonth.getMonth() ? 'bg-white' : 'bg-[var(--rr-background)]'}`}><span className={`text-xs ${date.getMonth() === viewMonth.getMonth() ? '' : 'text-[var(--rr-neutral-mid)]'}`}>{date.getDate()}</span><div className="mt-1 space-y-1">{items.slice(0, 2).map((item) => <p key={item.id} title={item.title} className="truncate rounded bg-[var(--rr-accent-light)] px-1 py-0.5 text-[9px] font-medium text-[var(--rr-primary)]">{item.platform || item.promotion_type || item.title}</p>)}{items.length > 2 && <span className="text-[9px]">+{items.length - 2} more</span>}</div></div>)}</div>
    </div>;
}

function Activity({ item }) {
    return <article className="flex flex-col gap-2 py-4 first:pt-0 sm:flex-row sm:items-start sm:justify-between">
        <div><h3 className="font-semibold">{item.title}</h3><p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">{item.platform || 'Program'}{item.promotion_type ? ` · ${item.promotion_type}` : ''}{item.business_name ? ` · ${item.business_name}` : ''}</p><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{item.scheduled_at ? new Date(item.scheduled_at).toLocaleString() : 'Date to be confirmed'}</p>{item.member_description && <p className="mt-2 text-sm leading-6">{item.member_description}</p>}{item.status === 'completed' && item.published_url && <a className="mt-2 inline-block text-xs font-semibold text-[var(--rr-primary)] underline" href={item.published_url} target="_blank" rel="noreferrer">View published content</a>}</div>
        <Status tone={item.status === 'completed' ? 'success' : item.status === 'cancelled' ? 'warning' : 'neutral'}>{item.status}</Status>
    </article>;
}
