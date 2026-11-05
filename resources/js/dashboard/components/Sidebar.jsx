import React from 'react';

export const navigation = [
    { key: 'overview', label: 'Overview', icon: '⌂', href: '/dashboard' },
    { key: 'business', label: 'Business profile', icon: '▤', href: '/dashboard/business' },
    { key: 'membership', label: 'Membership', icon: '◇', href: '/dashboard/membership' },
    { key: 'reports', label: 'Reports', icon: '▧', href: '/dashboard/reports' },
    { key: 'events', label: 'Events', icon: '▦', href: '/dashboard/events' },
    { key: 'resources', label: 'How-To Library', icon: '▱', href: '/dashboard/resources' },
    { key: 'guidelines', label: 'Guidelines', icon: '▤', href: '/dashboard/guidelines' },
    { key: 'marketing', label: 'Marketing schedule', icon: '✳', href: '/dashboard/marketing' },
    { key: 'settings', label: 'Settings', icon: '⚙', href: '/dashboard/settings' },
];

export default function Sidebar({ pageKey, menuOpen, setMenuOpen, navigate }) {
    return (
        <>
            {menuOpen && (
                <button
                    type="button"
                    className="fixed inset-0 z-40 bg-slate-950/45 lg:hidden"
                    aria-label="Close navigation menu"
                    onClick={() => setMenuOpen(false)}
                />
            )}
            <aside className={`fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-[var(--rr-primary)] text-white transition-transform duration-200 lg:translate-x-0 ${menuOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="flex h-20 items-center justify-between border-b border-white/10 px-6">
                    <a href="/" className="flex items-center gap-3 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-warning)]">
                        <img src="/the-argyle-buzz-logo.png" alt="The Argyle Buzz" width="475" height="88" className="h-10 w-auto max-w-[220px] rounded bg-white px-1" />
                    </a>
                    <button onClick={() => setMenuOpen(false)} className="min-h-11 min-w-11 rounded-lg text-white/80 hover:bg-white/10 lg:hidden" aria-label="Close navigation">×</button>
                </div>
                <p className="px-6 pb-3 pt-7 text-[10px] font-bold uppercase tracking-[0.18em] text-white/45">Your workspace</p>
                <nav className="flex-1 space-y-1 px-3" aria-label="Dashboard navigation">
                    {navigation.map((item) => {
                        const active = item.key === pageKey;

                        return (
                            <a
                                key={item.key}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                onClick={(event) => {
                                    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                                    event.preventDefault();
                                    navigate(item.href);
                                }}
                                className={`flex min-h-11 items-center gap-3 rounded-r-lg border-l-2 px-4 py-2.5 text-sm font-medium transition ${active ? 'border-[var(--rr-accent)] bg-white/10 text-white' : 'border-transparent text-white/70 hover:bg-white/5 hover:text-white'} focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-warning)]`}
                            >
                                <span className="flex size-6 items-center justify-center text-lg" aria-hidden="true">{item.icon}</span>
                                {item.label}
                            </a>
                        );
                    })}
                </nav>
                <div className="border-t border-white/10 p-5">
                    <a href="/" className="flex min-h-11 items-center rounded px-3 text-sm text-white/70 hover:bg-white/5 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-warning)]">
                        ← Back to public site
                    </a>
                </div>
            </aside>
        </>
    );
}
