import React, { useState } from 'react';

const moduleLabels = {
    overview: 'Overview',
    businesses: 'Business management',
    accounts: 'Accounts',
    memberships: 'Memberships',
    content: 'Content and resources',
    business: 'Business profile',
    membership: 'Membership',
    reports: 'Reports',
    events: 'Events',
    resources: 'How-To Library',
    guidelines: 'Guidelines',
    marketing: 'Marketing schedule',
    orders: 'Services and orders',
    billing: 'Billing and payments',
    analytics: 'Reporting and analytics',
    engagement: 'Member engagement',
    communications: 'Member communications',
    audit: 'Audit logs',
    settings: 'Settings',
    users: 'Members',
};

function defaultPermissions(modules) {
    return Object.fromEntries(modules.map((module) => [
        module,
        { read: module !== 'users', write: module !== 'users' },
    ]));
}

export default function AccountEditor({
    account, businessOptions, modules, memberModules, saving, error,
    canManageSystemRoles, canManagePermissions, onSave, onClose,
}) {
    const [form, setForm] = useState(() => ({
        first_name: account?.first_name ?? '',
        last_name: account?.last_name ?? '',
        email: account?.email ?? '',
        phone: account?.phone ?? '',
        job_title: account?.job_title ?? '',
        role: account?.role_key ?? 'member',
        business_roles: Object.fromEntries((account?.businesses ?? []).map((business) => [business.id, business.role])),
        password: '',
        password_confirmation: '',
        permissions: account?.permissions ?? defaultPermissions(account?.role_key === 'administrator' ? modules : memberModules),
    }));
    const [businessSearch, setBusinessSearch] = useState('');
    const visibleBusinesses = businessOptions.filter((business) =>
        business.name.toLowerCase().includes(businessSearch.trim().toLowerCase())
    );

    function changeRole(role) {
        setForm((current) => ({
            ...current,
            role,
            business_roles: role === 'member' ? current.business_roles : {},
            permissions: role === 'administrator'
                ? (current.role === 'administrator' ? current.permissions : defaultPermissions(modules))
                : role === 'member'
                    ? (current.role === 'member' ? current.permissions : defaultPermissions(memberModules))
                    : {},
        }));
    }

    function toggleBusiness(businessId, selected) {
        setForm((current) => {
            const businessRoles = { ...current.business_roles };
            if (selected) businessRoles[businessId] = 'member';
            else delete businessRoles[businessId];

            return { ...current, business_roles: businessRoles };
        });
    }

    function updateBusinessRole(businessId, role) {
        setForm((current) => ({
            ...current,
            business_roles: { ...current.business_roles, [businessId]: role },
        }));
    }

    function updatePermission(module, ability, checked) {
        setForm((current) => {
            const permission = { ...current.permissions[module], [ability]: checked };
            if (ability === 'read' && !checked) permission.write = false;

            return { ...current, permissions: { ...current.permissions, [module]: permission } };
        });
    }

    return (
        <div className="fixed inset-0 z-[60] overflow-y-auto bg-slate-950/50 p-4 sm:p-8" role="dialog" aria-modal="true" aria-labelledby="account-editor-title">
            <section className="mx-auto max-w-3xl rounded-2xl bg-white shadow-xl">
                <header className="flex items-start justify-between gap-4 border-b border-[var(--rr-border)] px-5 py-4 sm:px-7">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">{account ? 'Account details' : 'New account'}</p>
                        <h2 id="account-editor-title" className="mt-1 text-xl font-semibold">{account ? `Edit ${account.name}` : 'Create account'}</h2>
                    </div>
                    <button type="button" onClick={onClose} className="min-h-11 rounded-lg px-3 text-sm text-[var(--rr-neutral-mid)] hover:bg-[var(--rr-neutral-light)]">Close</button>
                </header>
                <form onSubmit={(event) => {
                    event.preventDefault();
                    const payload = { ...form };
                    payload.business_roles = payload.role === 'member'
                        ? Object.entries(payload.business_roles).map(([business_id, role]) => ({ business_id: Number(business_id), role }))
                        : [];
                    payload.business_ids = payload.business_roles.map(({ business_id }) => business_id);
                    if (!canManagePermissions || !['administrator', 'member'].includes(payload.role)) delete payload.permissions;
                    onSave(payload);
                }} className="space-y-6 p-5 sm:p-7">
                    {error && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-semibold">First name
                            <input className={fieldClass} required maxLength="120" value={form.first_name} onChange={(event) => setForm((current) => ({ ...current, first_name: event.target.value }))} />
                        </label>
                        <label className="text-sm font-semibold">Last name
                            <input className={fieldClass} maxLength="120" value={form.last_name} onChange={(event) => setForm((current) => ({ ...current, last_name: event.target.value }))} />
                        </label>
                        <label className="text-sm font-semibold sm:col-span-2">Email address
                            <input className={fieldClass} required type="email" maxLength="255" value={form.email} onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))} />
                        </label>
                        <label className="text-sm font-semibold">Phone
                            <input className={fieldClass} maxLength="40" value={form.phone} onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))} />
                        </label>
                        <label className="text-sm font-semibold">Job title
                            <input className={fieldClass} maxLength="120" value={form.job_title} onChange={(event) => setForm((current) => ({ ...current, job_title: event.target.value }))} />
                        </label>
                        <label className="text-sm font-semibold sm:col-span-2">Role
                            <select className={fieldClass} value={form.role} onChange={(event) => changeRole(event.target.value)}>
                                <option value="member">Member</option>
                                {canManageSystemRoles && <>
                                    <option value="administrator">Administrator</option>
                                    <option value="super_administrator">Super Administrator</option>
                                </>}
                            </select>
                        </label>
                    </div>

                    {form.role === 'member' && (
                        <fieldset className="rounded-xl border border-[var(--rr-border)] p-4">
                            <legend className="px-1 text-sm font-semibold">Business access</legend>
                            <p className="mb-3 text-xs leading-5 text-[var(--rr-neutral-mid)]">Business owner, administrator, and member roles are business-level access under the Member account type.</p>
                            {businessOptions.length ? (
                                <>
                                    <label className="mb-3 block text-xs font-semibold">
                                        Search businesses
                                        <input type="search" autoComplete="off" value={businessSearch} onChange={(event) => setBusinessSearch(event.target.value)} className={`${fieldClass} font-normal`} />
                                    </label>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                    {visibleBusinesses.map((business) => (
                                        <div key={business.id} className="flex min-h-10 items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                aria-label={`Associate ${business.name}`}
                                                checked={form.business_roles[business.id] !== undefined}
                                                onChange={(event) => toggleBusiness(business.id, event.target.checked)}
                                                className="size-4 accent-[var(--rr-primary)]"
                                            />
                                            <span className="min-w-0 flex-1">{business.name}</span>
                                            {form.business_roles[business.id] && (
                                                <select
                                                    aria-label={`${business.name} role`}
                                                    value={form.business_roles[business.id]}
                                                    onChange={(event) => updateBusinessRole(business.id, event.target.value)}
                                                    className="min-h-9 rounded-lg border border-[var(--rr-border)] bg-white px-2 text-xs"
                                                >
                                                    <option value="member">Member</option>
                                                    <option value="admin">Administrator</option>
                                                    <option value="owner">Owner</option>
                                                </select>
                                            )}
                                        </div>
                                    ))}
                                    </div>
                                    {visibleBusinesses.length === 0 && <p className="mt-2 text-sm text-[var(--rr-neutral-mid)]">No matching businesses.</p>}
                                </>
                            ) : <p className="text-sm text-[var(--rr-neutral-mid)]">Create a business before assigning a member.</p>}
                        </fieldset>
                    )}
                    {canManagePermissions && form.role === 'administrator' && (
                        <section className="rounded-xl border border-[var(--rr-border)] p-4">
                            <div className="mb-4">
                                <h3 className="font-semibold">System backend module access</h3>
                                <p className="mt-1 text-xs leading-5 text-[var(--rr-neutral-mid)]">This controls the Administrator's system backend access. Super Administrators manage these permissions.</p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[26rem] text-left text-sm">
                                    <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]">
                                        <tr><th className="py-2 font-semibold">Module</th><th className="py-2 text-center font-semibold">Read</th><th className="py-2 text-center font-semibold">Write</th></tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--rr-border)]">
                                        {modules.map((module) => (
                                            <tr key={module}>
                                                <th scope="row" className="py-2.5 font-medium">{moduleLabels[module] ?? module}</th>
                                                {['read', 'write'].map((ability) => (
                                                    <td key={ability} className="py-2.5 text-center">
                                                        <input
                                                            type="checkbox"
                                                            aria-label={`${moduleLabels[module] ?? module} ${ability}`}
                                                            checked={form.permissions[module]?.[ability] ?? false}
                                                            disabled={ability === 'write' && !form.permissions[module]?.read}
                                                            onChange={(event) => updatePermission(module, ability, event.target.checked)}
                                                            className="size-4 accent-[var(--rr-primary)]"
                                                        />
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    {canManagePermissions && form.role === 'member'
                        && Object.values(form.business_roles).some((role) => ['owner', 'admin'].includes(role)) && (
                            <section className="rounded-xl border border-[var(--rr-border)] p-4">
                                <div className="mb-4">
                                    <h3 className="font-semibold">Member dashboard module access</h3>
                                    <p className="mt-1 text-xs leading-5 text-[var(--rr-neutral-mid)]">These permissions apply to this Member's business access. Member management is off by default.</p>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[26rem] text-left text-sm">
                                        <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]">
                                            <tr><th className="py-2 font-semibold">Module</th><th className="py-2 text-center font-semibold">Read</th><th className="py-2 text-center font-semibold">Write</th></tr>
                                        </thead>
                                        <tbody className="divide-y divide-[var(--rr-border)]">
                                            {memberModules.map((module) => (
                                                <tr key={module}>
                                                    <th scope="row" className="py-2.5 font-medium">{moduleLabels[module] ?? module}</th>
                                                    {['read', 'write'].map((ability) => (
                                                        <td key={ability} className="py-2.5 text-center">
                                                            <input
                                                                type="checkbox"
                                                                aria-label={`${moduleLabels[module] ?? module} ${ability}`}
                                                                checked={form.permissions[module]?.[ability] ?? false}
                                                                disabled={ability === 'write' && !form.permissions[module]?.read}
                                                                onChange={(event) => updatePermission(module, ability, event.target.checked)}
                                                                className="size-4 accent-[var(--rr-primary)]"
                                                            />
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-semibold">{account ? 'New password (optional)' : 'Initial password'}
                            <input className={fieldClass} type="password" autoComplete="new-password" minLength="8" required={!account} value={form.password} onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))} />
                        </label>
                        <label className="text-sm font-semibold">Confirm password
                            <input className={fieldClass} type="password" autoComplete="new-password" minLength="8" required={!account || Boolean(form.password)} value={form.password_confirmation} onChange={(event) => setForm((current) => ({ ...current, password_confirmation: event.target.value }))} />
                        </label>
                    </div>
                    <div className="flex justify-end gap-3 border-t border-[var(--rr-border)] pt-4">
                        <button type="button" onClick={onClose} className="min-h-11 rounded-lg border border-[var(--rr-border)] px-4 text-sm font-semibold">Cancel</button>
                        <button type="submit" disabled={saving} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)] disabled:opacity-50">{saving ? 'Saving…' : account ? 'Save changes' : 'Create account'}</button>
                    </div>
                </form>
            </section>
        </div>
    );
}

const fieldClass = 'mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 py-2 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20';
