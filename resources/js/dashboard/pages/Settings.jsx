import React, { useState } from 'react';
import { Card, Definition, EmptyState, PageHeading } from '../components/UI';

export default function Settings({ data, onRefresh }) {
    const [optIn, setOptIn] = useState(data.user.email_announcements_opt_in);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const [editingProfile, setEditingProfile] = useState(false);
    const [profileMessage, setProfileMessage] = useState('');
    const [profileError, setProfileError] = useState('');
    const [profileErrors, setProfileErrors] = useState({});
    const [savingProfile, setSavingProfile] = useState(false);
    const [passwordMessage, setPasswordMessage] = useState('');
    const [passwordError, setPasswordError] = useState('');
    const [passwordErrors, setPasswordErrors] = useState({});
    const [savingPassword, setSavingPassword] = useState(false);

    const profileValues = {
        first_name: data.user.first_name ?? '',
        last_name: data.user.last_name ?? '',
        phone: data.user.phone ?? '',
        job_title: data.user.job_title ?? '',
        address_line_1: data.user.address_line_1 ?? '',
        address_line_2: data.user.address_line_2 ?? '',
        city: data.user.city ?? '',
        state: data.user.state ?? '',
        postal_code: data.user.postal_code ?? '',
        country: data.user.country ?? 'US',
    };

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

    async function saveProfile(event) {
        event.preventDefault();
        setSavingProfile(true);
        setProfileMessage('');
        setProfileError('');
        setProfileErrors({});

        try {
            const response = await fetch('/dashboard/account/profile', {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                setProfileErrors(payload.errors || {});
                throw new Error(payload.message || 'Profile could not be saved.');
            }
            setProfileMessage(payload.message);
            setEditingProfile(false);
            await onRefresh();
        } catch (requestError) {
            setProfileError(requestError.message);
        } finally {
            setSavingProfile(false);
        }
    }

    async function savePassword(event) {
        event.preventDefault();
        setSavingPassword(true);
        setPasswordMessage('');
        setPasswordError('');
        setPasswordErrors({});

        try {
            const response = await fetch('/dashboard/account/password', {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                setPasswordErrors(payload.errors || {});
                throw new Error(payload.message || 'Password could not be changed.');
            }
            event.currentTarget.reset();
            setPasswordMessage(payload.message);
        } catch (requestError) {
            setPasswordError(requestError.message);
        } finally {
            setSavingPassword(false);
        }
    }

    const fieldError = (errors, name) => errors[name]?.[0];

    return (
        <div>
            <PageHeading eyebrow="Account" title="Settings">
                Manage your personal information, account security, and member announcement preference.
            </PageHeading>
            <div className="grid gap-5 lg:grid-cols-2">
                <Card eyebrow="Personal information" title="Profile details" action={!editingProfile && <button type="button" onClick={() => setEditingProfile(true)} className="min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold">Edit</button>}>
                    {!editingProfile ? (
                        <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                            {[
                                ['First name', data.user.first_name],
                                ['Last name', data.user.last_name],
                                ['Email address', data.user.email],
                                ['Phone', data.user.phone],
                                ['Job title', data.user.job_title],
                                ['Address', data.user.address_line_1],
                                ['Address line 2', data.user.address_line_2],
                                ['City', data.user.city],
                                ['State / region', data.user.state],
                                ['Postal code', data.user.postal_code],
                                ['Country', data.user.country],
                                ['Role', data.user.is_super_admin ? 'Super Administrator' : data.user.dashboard_role === 'administrator' ? 'Administrator' : 'Member'],
                            ].map(([label, value]) => <Definition key={label} label={label} value={value} />)}
                        </dl>
                    ) : (
                        <form onSubmit={saveProfile} className="mt-5 grid gap-4 sm:grid-cols-2">
                            {[
                                ['first_name', 'First name', 'text', true],
                                ['last_name', 'Last name', 'text', false],
                                ['phone', 'Contact number', 'tel', false],
                                ['job_title', 'Job title', 'text', false],
                                ['address_line_1', 'Address', 'text', false],
                                ['address_line_2', 'Address line 2', 'text', false],
                                ['city', 'City', 'text', false],
                                ['state', 'State / region', 'text', false],
                                ['postal_code', 'Postal code', 'text', false],
                                ['country', 'Country code', 'text', false],
                            ].map(([name, label, type, required]) => (
                                <label key={name} className="text-sm font-medium">
                                    {label}
                                    <input name={name} type={type} required={required} defaultValue={profileValues[name]} maxLength={name === 'country' ? 2 : undefined} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                                    {fieldError(profileErrors, name) && <span className="mt-1 block text-xs text-red-700">{fieldError(profileErrors, name)}</span>}
                                </label>
                            ))}
                            {profileError && <p role="alert" className="sm:col-span-2 text-sm text-red-700">{profileError}</p>}
                            <div className="sm:col-span-2 flex gap-2">
                                <button disabled={savingProfile} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white disabled:opacity-50">{savingProfile ? 'Saving…' : 'Save changes'}</button>
                                <button type="button" disabled={savingProfile} onClick={() => { setEditingProfile(false); setProfileErrors({}); setProfileError(''); }} className="min-h-11 rounded-lg border border-[var(--rr-border)] px-4 text-sm font-semibold">Cancel</button>
                            </div>
                        </form>
                    )}
                    {profileMessage && <p role="status" className="mt-4 text-sm text-green-800">{profileMessage}</p>}
                </Card>
                <Card eyebrow="Account security" title="Change password">
                    <form onSubmit={savePassword} className="mt-5 grid gap-4">
                        {[
                            ['current_password', 'Current password'],
                            ['password', 'New password'],
                            ['password_confirmation', 'Confirm new password'],
                        ].map(([name, label]) => (
                            <label key={name} className="text-sm font-medium">
                                {label}
                                <input name={name} type="password" required autoComplete={name === 'current_password' ? 'current-password' : 'new-password'} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                                {fieldError(passwordErrors, name) && <span className="mt-1 block text-xs text-red-700">{fieldError(passwordErrors, name)}</span>}
                            </label>
                        ))}
                        <p className="text-xs text-[var(--rr-neutral-mid)]">Use at least 8 characters.</p>
                        {passwordError && <p role="alert" className="text-sm text-red-700">{passwordError}</p>}
                        {passwordMessage && <p role="status" className="text-sm text-green-800">{passwordMessage}</p>}
                        <button disabled={savingPassword} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white disabled:opacity-50">{savingPassword ? 'Changing…' : 'Change password'}</button>
                    </form>
                    <p className="mt-5 border-t border-[var(--rr-border)] pt-4 text-xs leading-5 text-[var(--rr-neutral-mid)]">
                        Login email: {data.user.email}. Email changes require support until email verification is available in this dashboard.
                    </p>
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
