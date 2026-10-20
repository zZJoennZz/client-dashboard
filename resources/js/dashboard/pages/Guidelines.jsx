import React, { useMemo, useState } from 'react';
import { Card, EmptyState, PageHeading, Status } from '../components/UI';

export default function Guidelines({ data, onRefresh }) {
    const guidelines = (data.resources ?? []).filter((resource) => resource.resource_group === 'guideline');
    const [busyId, setBusyId] = useState(null);
    const requiredUnread = guidelines.filter((resource) => resource.acknowledgment_required && !resource.acknowledged_at);
    const grouped = useMemo(() => guidelines.reduce((result, resource) => {
        const category = resource.category || 'General';
        result[category] = [...(result[category] ?? []), resource];
        return result;
    }, {}), [guidelines]);

    async function acknowledge(resource) {
        setBusyId(resource.id);
        try {
            const response = await fetch(`/dashboard/resources/${resource.id}/acknowledge`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            });
            if (!response.ok) throw new Error('Acknowledgment could not be saved. Please try again.');
            await onRefresh();
        } catch (error) {
            window.alert(error.message);
        } finally {
            setBusyId(null);
        }
    }

    return <div className="space-y-5">
        <PageHeading eyebrow="Member resources" title="Guidelines & expectations">Program requirements, brand standards, and promotion guidance.</PageHeading>
        {requiredUnread.length > 0 && <div role="alert" className="border-l-4 border-amber-500 bg-amber-50 p-4 text-amber-950"><p className="font-semibold">{requiredUnread.length} required {requiredUnread.length === 1 ? 'guideline needs' : 'guidelines need'} your acknowledgment</p><p className="mt-1 text-sm">Review each document and mark it as read to keep your business guidance current.</p></div>}
        {guidelines.length === 0 ? <EmptyState title="No guidelines available">Published program guidelines will appear here.</EmptyState> : Object.entries(grouped).sort(([left], [right]) => left.localeCompare(right)).map(([category, items]) => <section key={category} className="space-y-3"><h2 className="border-b border-[var(--rr-border)] pb-2 text-base font-semibold">{category}</h2>{items.map((resource) => <Card key={resource.id} className="shadow-none">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="font-semibold">{resource.title}</h3>{resource.acknowledgment_required && <Status tone={resource.acknowledged_at ? 'success' : 'warning'}>{resource.acknowledged_at ? 'Acknowledged' : 'Acknowledgment required'}</Status>}</div>{resource.description && <p className="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{resource.description}</p>}<p className="mt-2 text-xs text-[var(--rr-neutral-mid)]">Version {resource.version}{resource.acknowledged_at ? ` · Acknowledged ${new Date(resource.acknowledged_at).toLocaleDateString()}` : ''}</p>{resource.file_name && <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{resource.file_name}</p>}</div><div className="flex shrink-0 flex-wrap gap-2">{(resource.download_url || resource.file_url) && <a className="inline-flex min-h-10 items-center rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold" href={resource.download_url || resource.file_url} target="_blank" rel="noreferrer">Open document</a>}{resource.acknowledgment_required && !resource.acknowledged_at && <button disabled={busyId === resource.id} onClick={() => acknowledge(resource)} className="min-h-10 rounded-lg bg-[var(--rr-primary)] px-3 text-xs font-semibold text-white disabled:opacity-50">{busyId === resource.id ? 'Saving…' : 'Mark as read'}</button>}</div></div>
            {resource.content && <details className="mt-4 border-t border-[var(--rr-border)] pt-3"><summary className="cursor-pointer text-xs font-semibold text-[var(--rr-primary)]">Read document text</summary><div className="mt-3 whitespace-pre-wrap text-sm leading-6">{resource.content}</div></details>}
        </Card>)}</section>)}
    </div>;
}