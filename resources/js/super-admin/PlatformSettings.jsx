import React, { useCallback, useEffect, useState } from 'react';

export default function PlatformSettings({ url, updateUrl, requestJson }) {
    const [data, setData] = useState(null);
    const [form, setForm] = useState({ amount: '', currency_symbol: '$', period: 'year' });
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    const load = useCallback(async () => {
        setLoading(true);
        setError('');
        try {
            const response = await requestJson(url);
            setData(response);
            setForm({
                amount: response.membership_pricing.amount ?? '',
                currency_symbol: response.membership_pricing.currency_symbol ?? '$',
                period: response.membership_pricing.period === 'month' ? 'month' : 'year',
            });
        } catch (requestError) {
            setError(requestError.message || 'Unable to load platform settings.');
        } finally {
            setLoading(false);
        }
    }, [requestJson, url]);

    useEffect(() => { load(); }, [load]);

    async function save(event) {
        event.preventDefault();
        setSaving(true);
        setError('');
        setMessage('');
        try {
            const response = await requestJson(updateUrl, {
                method: 'PUT',
                body: JSON.stringify({
                    amount: form.amount === '' ? null : form.amount,
                    period: form.period,
                }),
            });
            setData((current) => ({
                ...current,
                membership_pricing: response.membership_pricing,
            }));
            setForm((current) => ({
                ...current,
                amount: response.membership_pricing.amount ?? '',
            }));
            setMessage(response.message);
        } catch (requestError) {
            setError(requestError.message || 'Unable to save membership pricing.');
        } finally {
            setSaving(false);
        }
    }

    if (loading && !data) return <p className="rounded-xl border border-[var(--rr-border)] bg-white p-6 text-sm">Loading platform settings…</p>;
    if (error && !data) return <div role="alert" className="rounded-xl border border-red-200 bg-white p-6"><p className="font-semibold text-red-700">Platform settings are unavailable</p><p className="mt-2 text-sm">{error}</p><button type="button" onClick={load} className="mt-4 min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white">Retry</button></div>;

    return (
        <div className="space-y-6">
            <header>
                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">Platform control</p>
                <h2 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Platform settings</h2>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-[var(--rr-neutral-mid)]">Manage platform-wide settings here. These changes apply across the public site and all member businesses.</p>
            </header>
            {message && <p role="status" className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">{message}</p>}
            {error && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}
            <section className="rounded-xl border border-[var(--rr-border)] bg-white p-5 shadow-sm sm:p-6">
                <div className="max-w-2xl">
                    <p className="text-[10px] font-bold uppercase tracking-[0.15em] text-[var(--rr-accent)]">Public website</p>
                    <h3 className="mt-1 text-lg font-semibold">Membership pricing</h3>
                    <p className="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">Controls the price shown on public pricing pages and the plan assigned to future registrations. It does not collect money or change existing memberships.</p>
                </div>
                <form onSubmit={save} className="mt-6 grid gap-4 sm:grid-cols-2">
                    <label className="text-sm font-semibold">Amount
                        <div className="mt-1 flex min-h-11 items-center overflow-hidden rounded-lg border border-[var(--rr-border)] focus-within:border-[var(--rr-primary)] focus-within:ring-2 focus-within:ring-[var(--rr-primary)]/20">
                            <span className="px-3 text-sm text-[var(--rr-neutral-mid)]">{form.currency_symbol || '$'}</span>
                            <input aria-label="Membership amount" className="min-h-11 w-full border-0 py-2 pl-0 pr-3 text-sm focus:outline-none focus:ring-0" type="number" min="0" max="999999.99" step="0.01" value={form.amount} onChange={(event) => setForm((current) => ({ ...current, amount: event.target.value }))} placeholder="Leave blank to show pricing coming soon" />
                        </div>
                    </label>
                    <label className="text-sm font-semibold">Billing period
                        <select aria-label="Billing period" className={fieldClass} value={form.period} onChange={(event) => setForm((current) => ({ ...current, period: event.target.value }))}>
                            <option value="month">Monthly</option>
                            <option value="year">Yearly</option>
                        </select>
                    </label>
                    <div className="flex flex-wrap items-center gap-3 sm:col-span-2">
                        <button type="submit" disabled={saving} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)] disabled:opacity-50">{saving ? 'Saving…' : 'Save membership pricing'}</button>
                        <button type="button" onClick={load} disabled={saving} className="min-h-11 rounded-lg border border-[var(--rr-border)] px-4 text-sm font-semibold hover:bg-[var(--rr-neutral-light)] disabled:opacity-50">Reload</button>
                    </div>
                </form>
                {/* <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">Currency symbol is controlled by deployment configuration. Registration continues to use the existing simulated paid status. This is a placeholder only; no payment is verified or collected. Changing this price does not alter prior membership plan records.</p> */}
            </section>
            <section className="rounded-xl border border-dashed border-[var(--rr-border)] bg-white p-5 sm:p-6">
                <p className="text-[10px] font-bold uppercase tracking-[0.15em] text-[var(--rr-neutral-mid)]">Later phase</p>
                <h3 className="mt-1 text-lg font-semibold">Public page content management</h3>
                <p className="mt-2 max-w-2xl text-sm leading-6 text-[var(--rr-neutral-mid)]">{data?.website_content?.description || 'Editing public page content will be added in a later phase.'} Public pages remain Blade-rendered.</p>
                <span className="mt-4 inline-flex rounded-full bg-[var(--rr-neutral-light)] px-3 py-1 text-xs font-semibold text-[var(--rr-neutral-mid)]">Not available yet</span>
            </section>
        </div>
    );
}

const fieldClass = 'mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 py-2 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20';
