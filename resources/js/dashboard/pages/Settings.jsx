import React, { useState } from 'react';
import { Card, Definition, EmptyState, PageHeading } from '../components/UI';

export default function Settings({ data }) {
    const [optIn, setOptIn] = useState(data.user.email_announcements_opt_in);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    async function savePreference(event) {
        event.preventDefault();
        setSaving(true);
        setMessage('');
        setError('');
        try {
            const response = await fetch('/dashboard/email-preferences', {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ email_announcements_opt_in: optIn }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'Preference could not be saved.');
            setMessage('Email preference saved.');
        } catch (requestError) {
            setError(requestError.message);
        } finally {
            setSaving(false);
        }
    }

    return (
        <div>
            <PageHeading eyebrow="Account" title="Settings">
                Account details are read-only here. You can manage your member announcement email preference.
            </PageHeading>
            <div className="grid gap-5 lg:grid-cols-2">
                <Card eyebrow="Signed-in account" title="Login details">
                    <dl className="mt-5 space-y-5">
                        <Definition label="First name" value={data.user.first_name} />
                        <Definition label="Last name" value={data.user.last_name} />
                        <Definition label="Email address" value={data.user.email} />
                        <Definition label="Phone" value={data.user.phone} />
                        <Definition label="Job title" value={data.user.job_title} />
                        <Definition label="Address" value={data.user.address_line_1} />
                        <Definition label="Address line 2" value={data.user.address_line_2} />
                        <Definition label="City" value={data.user.city} />
                        <Definition label="State / region" value={data.user.state} />
                        <Definition label="Postal code" value={data.user.postal_code} />
                        <Definition label="Country" value={data.user.country} />
                        <Definition label="Account type" value={data.user.is_super_admin ? 'Service provider' : 'Regular account'} />
                    </dl>
                    <p className="mt-5 border-t border-[var(--rr-border)] pt-4 text-xs leading-5 text-[var(--rr-neutral-mid)]">To change your login email or password, use the account support process. Self-service editing is not available in this dashboard.</p>
                </Card>
                <Card eyebrow="Business" title="Account preferences">
                    {data.business ? (
                        <dl className="mt-5 space-y-5">
                            <Definition label="Business" value={data.business.name} />
                            <Definition label="Your role" value={data.business.role} />
                            <Definition label="Business email" value={data.business.contact_email} />
                        </dl>
                    ) : (
                        <div className="mt-4"><EmptyState title="No associated business">Business preferences will be available after a business is connected to your account.</EmptyState></div>
                    )}
                    <form onSubmit={savePreference} className="mt-5 border-t border-[var(--rr-border)] pt-4">
                        <label className="flex items-start gap-3 text-sm leading-6">
                            <input type="checkbox" checked={optIn} onChange={(event) => setOptIn(event.target.checked)} className="mt-1 min-h-5 min-w-5 accent-[var(--rr-primary)]" />
                            <span>Receive member announcements by email. You can unsubscribe here at any time.</span>
                        </label>
                        {message && <p role="status" className="mt-3 text-sm text-green-800">{message}</p>}
                        {error && <p role="alert" className="mt-3 text-sm text-red-700">{error}</p>}
                        <button disabled={saving} className="mt-3 min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white disabled:opacity-50">{saving ? 'Saving…' : 'Save preference'}</button>
                    </form>
                </Card>
            </div>
        </div>
    );
}
