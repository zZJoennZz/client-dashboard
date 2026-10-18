import React from 'react';

export default function Topbar({ data, pageTitle, setMenuOpen }) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    return (
        <header className="sticky top-0 z-30 border-b border-[var(--rr-border)] bg-white/95 backdrop-blur">
            <div className="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
                <div className="flex min-w-0 items-center gap-3">
                    <button
                        onClick={() => setMenuOpen(true)}
                        className="flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-[var(--rr-border)] text-[var(--rr-primary)] hover:bg-[var(--rr-neutral-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)] lg:hidden"
                        aria-label="Open navigation menu"
                    >
                        <span aria-hidden="true">☰</span>
                    </button>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-[var(--rr-neutral-dark)]">{pageTitle}</p>
                        <p className="hidden truncate text-xs text-[var(--rr-neutral-mid)] sm:block">{data?.user?.is_super_admin ? 'All businesses' : data?.business?.name ?? 'Rediscover Roanoke member workspace'}</p>
                    </div>
                </div>
                <details className="relative">
                    <summary className="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1 hover:bg-[var(--rr-neutral-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">
                        <span className="flex size-9 items-center justify-center rounded-full bg-[var(--rr-accent-light)] text-sm font-bold text-[var(--rr-accent)]" aria-hidden="true">
                            {data?.user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                        </span>
                        <span className="hidden max-w-36 truncate text-left text-sm font-medium sm:block">{data?.user?.name ?? 'Member'}</span>
                        <span className="text-xs text-[var(--rr-neutral-mid)]" aria-hidden="true">⌄</span>
                    </summary>
                    <div className="absolute right-0 top-12 z-40 w-64 rounded-xl border border-[var(--rr-border)] bg-white p-4 shadow-lg">
                        <p className="truncate text-sm font-semibold">{data?.user?.name}</p>
                        <p className="mt-1 truncate text-xs text-[var(--rr-neutral-mid)]">{data?.user?.email}</p>
                        {data?.user?.is_super_admin && <p className="mt-2 inline-flex rounded-full bg-[var(--rr-accent-light)] px-2.5 py-1 text-xs font-semibold text-[var(--rr-accent)]">Service provider</p>}
                        <form method="POST" action="/logout" className="mt-3 border-t border-[var(--rr-border)] pt-3">
                            <input type="hidden" name="_token" value={token} />
                            <button type="submit" className="min-h-11 w-full rounded-lg px-3 text-left text-sm font-semibold text-[var(--rr-primary)] hover:bg-[var(--rr-neutral-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">Log out</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>
    );
}
