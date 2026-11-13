import React, { useEffect, useState } from 'react';
import { Card, EmptyState, PageHeading } from '../components/UI';

async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.body ? {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            } : {}),
            ...options.headers,
        },
    });
    if (response.status === 401 || response.redirected) {
        window.location.assign('/login');
        throw new Error('Your session has expired. Please sign in again.');
    }

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = Object.values(payload.errors ?? {}).flat();
        throw new Error(errors.join(' ') || payload.message || `The request failed (HTTP ${response.status}).`);
    }

    return payload;
}

export default function Members({ membersUrl: url }) {
    const [members, setMembers] = useState([]);
    const [canWrite, setCanWrite] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [selected, setSelected] = useState(null);
    const [form, setForm] = useState({ first_name: '', last_name: '', phone: '', job_title: '' });
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');

    async function load() {
        setLoading(true);
        setError('');
        try {
            const response = await requestJson(url);
            setMembers(response.data);
            setCanWrite(response.can_write);
        } catch (requestError) {
            setError(requestError.message || 'Member accounts could not be loaded.');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => { load(); }, [url]);

    function edit(member) {
        setSelected(member);
        setForm({
            first_name: member.first_name ?? '',
            last_name: member.last_name ?? '',
            phone: member.phone ?? '',
            job_title: member.job_title ?? '',
        });
        setMessage('');
        setError('');
    }

    async function save(event) {
        event.preventDefault();
        setSaving(true);
        setError('');
        setMessage('');
        try {
            await requestJson(`${url}/${selected.id}`, {
                method: 'PUT',
                body: JSON.stringify(form),
            });
            setSelected(null);
            setMessage(`Member profile saved for ${form.first_name} ${form.last_name}.`);
            await load();
        } catch (requestError) {
            setError(requestError.message || 'Member profile could not be saved.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="space-y-6">
            <PageHeading eyebrow="Business access" title="Members">
                Review members associated with your businesses. Read access shows the list; Write access allows you to update member profile details.
            </PageHeading>
            {message && <p role="status" className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">{message}</p>}
            {error && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}
            <Card>
                {loading ? <p className="text-sm text-[var(--rr-neutral-mid)]">Loading members…</p> : members.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[36rem] text-left text-sm">
                            <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]">
                                <tr><th className="pb-3 pr-4 font-semibold">Member</th><th className="pb-3 pr-4 font-semibold">Business</th><th className="pb-3 pr-4 font-semibold">Job title</th>{canWrite && <th className="pb-3 text-right font-semibold">Action</th>}</tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--rr-border)]">
                                {members.map((member) => (
                                    <tr key={member.id}>
                                        <td className="py-4 pr-4"><p className="font-semibold">{member.name}</p><p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{member.email}</p></td>
                                        <td className="py-4 pr-4">{member.businesses.join(', ')}</td>
                                        <td className="py-4 pr-4">{member.job_title || '—'}</td>
                                        {canWrite && <td className="py-4 text-right"><button type="button" onClick={() => edit(member)} className="min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)]">Edit member</button></td>}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : <EmptyState title="No members found">Members linked to your businesses will appear here.</EmptyState>}
            </Card>

            {selected && (
                <div className="fixed inset-0 z-[60] overflow-y-auto bg-slate-950/50 p-4 sm:p-8" role="dialog" aria-modal="true" aria-labelledby="member-editor-title">
                    <section className="mx-auto max-w-xl rounded-2xl bg-white shadow-xl">
                        <header className="border-b border-[var(--rr-border)] px-5 py-4">
                            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">Member profile</p>
                            <h2 id="member-editor-title" className="mt-1 text-xl font-semibold">Edit {selected.name}</h2>
                            <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">{selected.email} · {selected.businesses.join(', ')}</p>
                        </header>
                        <form onSubmit={save} className="space-y-4 p-5">
                            {error && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}
                            <label className="block text-sm font-semibold">First name<input required maxLength="120" className={fieldClass} value={form.first_name} onChange={(event) => setForm((current) => ({ ...current, first_name: event.target.value }))} /></label>
                            <label className="block text-sm font-semibold">Last name<input maxLength="120" className={fieldClass} value={form.last_name} onChange={(event) => setForm((current) => ({ ...current, last_name: event.target.value }))} /></label>
                            <label className="block text-sm font-semibold">Phone<input maxLength="40" className={fieldClass} value={form.phone} onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))} /></label>
                            <label className="block text-sm font-semibold">Job title<input maxLength="120" className={fieldClass} value={form.job_title} onChange={(event) => setForm((current) => ({ ...current, job_title: event.target.value }))} /></label>
                            <div className="flex justify-end gap-3 border-t border-[var(--rr-border)] pt-4">
                                <button type="button" onClick={() => setSelected(null)} className="min-h-11 rounded-lg border border-[var(--rr-border)] px-4 text-sm font-semibold">Cancel</button>
                                <button type="submit" disabled={saving} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-5 text-sm font-semibold text-white disabled:opacity-50">{saving ? 'Saving…' : 'Save member'}</button>
                            </div>
                        </form>
                    </section>
                </div>
            )}
        </div>
    );
}

const fieldClass = 'mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 py-2 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20';
