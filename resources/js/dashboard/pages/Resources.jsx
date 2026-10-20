import React, { useMemo, useState } from 'react';
import { Card, EmptyState, PageHeading } from '../components/UI';

export default function Resources({ data }) {
    const resources = data.resources ?? [];
    const library = resources.filter((resource) => resource.resource_group === 'how_to');
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [selected, setSelected] = useState(null);
    const categories = [...new Set(library.flatMap((resource) => resource.tags ?? [resource.category]).filter(Boolean))].sort();
    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        return library.filter((resource) => {
            const searchable = [resource.title, resource.description, resource.content, resource.category, ...(resource.tags ?? [])].join(' ').toLowerCase();
            return (!term || searchable.includes(term)) && (category === 'all' || (resource.tags ?? [resource.category]).includes(category));
        });
    }, [library, search, category]);

    async function openResource(resource) {
        setSelected(resource);
        await fetch(`/dashboard/activity/resources/${resource.id}/viewed`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
        }).catch(() => {});
    }

    return <div className="space-y-5">
        <PageHeading eyebrow="Member resources" title="How-To Library">Practical ideas and guides for promoting and improving your business.</PageHeading>
        <Card>
            <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end"><label className="text-xs font-semibold">Search the library<input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search titles, topics, and content" className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 text-sm" /></label><div><p className="mb-1 text-xs font-semibold">Category</p><div className="flex max-w-full flex-wrap gap-2"><button onClick={() => setCategory('all')} className={`min-h-9 rounded-full border px-3 text-xs font-semibold ${category === 'all' ? 'border-[var(--rr-primary)] bg-[var(--rr-primary)] text-white' : 'border-[var(--rr-border)]'}`}>All</button>{categories.map((item) => <button key={item} onClick={() => setCategory(item)} className={`min-h-9 rounded-full border px-3 text-xs font-semibold ${category === item ? 'border-[var(--rr-primary)] bg-[var(--rr-primary)] text-white' : 'border-[var(--rr-border)]'}`}>{item}</button>)}</div></div></div>
        </Card>
        {filtered.length === 0 ? <EmptyState title="No how-to materials found">Published articles, videos, and guides will appear here.</EmptyState> : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{filtered.map((resource) => <Card key={resource.id} className="shadow-none"><article className="flex h-full flex-col"><div className="flex items-center justify-between gap-3"><span className="text-lg" aria-hidden="true">{resource.resource_type === 'video' ? '▶' : resource.resource_type === 'pdf' ? '▤' : resource.resource_type === 'link' ? '↗' : '¶'}</span><span className="text-xs text-[var(--rr-neutral-mid)]">{resource.estimated_minutes ? `${resource.estimated_minutes} min ${resource.resource_type === 'video' ? 'watch' : 'read'}` : resource.resource_type}</span></div><h2 className="mt-3 text-base font-semibold">{resource.title}</h2><p className="mt-2 flex-1 text-sm leading-6 text-[var(--rr-neutral-mid)]">{resource.description || 'Open this resource for details.'}</p><div className="mt-4 flex flex-wrap gap-1.5">{(resource.tags?.length ? resource.tags : [resource.category]).filter(Boolean).map((tag) => <span key={tag} className="rounded-full bg-[var(--rr-neutral-light)] px-2.5 py-1 text-[10px] font-medium">{tag}</span>)}</div><button onClick={() => openResource(resource)} className="mt-4 min-h-10 self-start text-xs font-semibold text-[var(--rr-primary)] underline">Open material</button></article></Card>)}</div>}
        {selected && <div role="dialog" aria-modal="true" aria-labelledby="resource-title" className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-5" onClick={() => setSelected(null)}><section className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-t-xl bg-white p-5 sm:rounded-xl sm:p-7" onClick={(event) => event.stopPropagation()}><div className="flex items-start justify-between gap-4"><div><p className="text-xs font-semibold uppercase text-[var(--rr-accent)]">{selected.category}</p><h2 id="resource-title" className="mt-1 text-xl font-semibold">{selected.title}</h2></div><button aria-label="Close resource" onClick={() => setSelected(null)} className="min-h-10 min-w-10 rounded-lg border border-[var(--rr-border)]">×</button></div>{selected.resource_type === 'video' && selected.video_embed_url && <div className="mt-5 aspect-video overflow-hidden bg-black"><iframe title={selected.title} src={selected.video_embed_url} className="h-full w-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowFullScreen /></div>}{selected.content && <div className="mt-5 whitespace-pre-wrap text-sm leading-7">{selected.content}</div>}{!selected.content && selected.description && <p className="mt-5 text-sm leading-6">{selected.description}</p>}{(selected.download_url || selected.file_url) && <a className="mt-5 inline-flex min-h-10 items-center rounded-lg bg-[var(--rr-primary)] px-4 text-xs font-semibold text-white" href={selected.download_url || selected.file_url} target="_blank" rel="noreferrer">Open attached resource</a>}</section></div>}
    </div>;
}
