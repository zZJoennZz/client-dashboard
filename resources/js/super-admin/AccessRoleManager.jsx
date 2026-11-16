import React, { useEffect, useState } from 'react';

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

export default function AccessRoleManager({
    url, roles, modules, memberModules, requestJson, onChanged, onClose,
}) {
    const [editingRole, setEditingRole] = useState(null);
    const [name, setName] = useState('');
    const [appliesTo, setAppliesTo] = useState('administrator');
    const [permissions, setPermissions] = useState(defaultPermissions(modules));
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!editingRole) return;
        setName(editingRole.name);
        setAppliesTo(editingRole.applies_to);
        setPermissions(editingRole.permissions);
    }, [editingRole]);

    const availableModules = appliesTo === 'administrator' ? modules : memberModules;

    function startNewRole() {
        setEditingRole(null);
        setName('');
        setAppliesTo('administrator');
        setPermissions(defaultPermissions(modules));
        setError('');
    }

    function changeAppliesTo(value) {
        setAppliesTo(value);
        setPermissions(defaultPermissions(value === 'administrator' ? modules : memberModules));
    }

    function updatePermission(module, ability, checked) {
        setPermissions((current) => {
            const permission = { ...current[module], [ability]: checked };
            if (ability === 'read' && !checked) permission.write = false;
            return { ...current, [module]: permission };
        });
    }

    async function saveRole(event) {
        event.preventDefault();
        setSaving(true);
        setError('');
        try {
            await requestJson(editingRole ? `${url}/${editingRole.id}` : url, {
                method: editingRole ? 'PUT' : 'POST',
                body: JSON.stringify({ name, applies_to: appliesTo, permissions }),
            });
            startNewRole();
            await onChanged();
        } catch (requestError) {
            setError(requestError.message || 'The role could not be saved.');
        } finally {
            setSaving(false);
        }
    }

    async function deleteRole(role) {
        if (!window.confirm(`Delete the "${role.name}" role?`)) return;
        setError('');
        try {
            await requestJson(`${url}/${role.id}`, { method: 'DELETE' });
            if (editingRole?.id === role.id) startNewRole();
            await onChanged();
        } catch (requestError) {
            setError(requestError.message || 'The role could not be deleted.');
        }
    }

    return (
        <div className="fixed inset-0 z-[70] overflow-y-auto bg-slate-950/50 p-4 sm:p-8" role="dialog" aria-modal="true" aria-labelledby="access-role-title">
            <section className="mx-auto max-w-5xl rounded-2xl bg-white shadow-xl">
                <header className="flex items-start justify-between gap-4 border-b border-[var(--rr-border)] px-5 py-4 sm:px-7">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">Access presets</p>
                        <h2 id="access-role-title" className="mt-1 text-xl font-semibold">Manage roles</h2>
                    </div>
                    <button type="button" onClick={onClose} className="min-h-11 rounded-lg px-3 text-sm text-[var(--rr-neutral-mid)] hover:bg-[var(--rr-neutral-light)]">Close</button>
                </header>
                <div className="grid gap-6 p-5 sm:p-7 lg:grid-cols-[minmax(16rem,0.8fr)_minmax(0,1.2fr)]">
                    <section>
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <h3 className="font-semibold">Saved roles</h3>
                            <button type="button" onClick={startNewRole} className="min-h-9 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold">New role</button>
                        </div>
                        {roles.length ? (
                            <ul className="divide-y divide-[var(--rr-border)] rounded-xl border border-[var(--rr-border)]">
                                {roles.map((role) => (
                                    <li key={role.id} className="flex items-center justify-between gap-3 p-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold">{role.name}</p>
                                            <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">
                                                {role.applies_to === 'administrator' ? 'Administrator' : 'Member access'}
                                                {role.users_count ? ` · ${role.users_count} assigned` : ''}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 gap-2">
                                            <button type="button" onClick={() => setEditingRole(role)} className="min-h-9 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold">Edit</button>
                                            <button type="button" onClick={() => deleteRole(role)} className="min-h-9 rounded-lg border border-red-200 px-3 text-xs font-semibold text-red-700">Delete</button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : <p className="rounded-xl border border-dashed border-[var(--rr-border)] p-4 text-sm text-[var(--rr-neutral-mid)]">No roles created yet.</p>}
                    </section>
                    <form onSubmit={saveRole} className="space-y-4">
                        <h3 className="font-semibold">{editingRole ? `Edit ${editingRole.name}` : 'Create role'}</h3>
                        {error && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}
                        <label className="block text-sm font-semibold">Role name
                            <input className={fieldClass} required maxLength="80" value={name} onChange={(event) => setName(event.target.value)} />
                        </label>
                        <label className="block text-sm font-semibold">For account type
                            <select className={fieldClass} value={appliesTo} disabled={Boolean(editingRole?.users_count)} onChange={(event) => changeAppliesTo(event.target.value)}>
                                <option value="administrator">Administrator</option>
                                <option value="member">Member access</option>
                            </select>
                        </label>
                        {editingRole?.users_count > 0 && <p className="text-xs text-[var(--rr-neutral-mid)]">The account type cannot change while this role is assigned.</p>}
                        <div className="overflow-x-auto rounded-xl border border-[var(--rr-border)] p-3">
                            <table className="w-full min-w-[22rem] text-left text-sm">
                                <thead className="text-xs uppercase tracking-wide text-[var(--rr-neutral-mid)]">
                                    <tr><th className="py-2 font-semibold">Module</th><th className="py-2 text-center font-semibold">Read</th><th className="py-2 text-center font-semibold">Write</th></tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--rr-border)]">
                                    {availableModules.map((module) => (
                                        <tr key={module}>
                                            <th scope="row" className="py-2.5 font-medium">{moduleLabels[module] ?? module}</th>
                                            {['read', 'write'].map((ability) => (
                                                <td key={ability} className="py-2.5 text-center">
                                                    <input
                                                        type="checkbox"
                                                        aria-label={`${moduleLabels[module] ?? module} ${ability}`}
                                                        checked={permissions[module]?.[ability] ?? false}
                                                        disabled={ability === 'write' && !permissions[module]?.read}
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
                        <div className="flex justify-end gap-3 border-t border-[var(--rr-border)] pt-4">
                            <button type="submit" disabled={saving} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-5 text-sm font-semibold text-white disabled:opacity-50">{saving ? 'Saving…' : editingRole ? 'Save role' : 'Create role'}</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    );
}

const fieldClass = 'mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 py-2 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20';
