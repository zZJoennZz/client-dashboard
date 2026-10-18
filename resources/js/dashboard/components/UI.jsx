import React from 'react';

export function Card({ title, eyebrow, action, children, className = '' }) {
    return (
        <section className={`rounded-xl border border-[var(--rr-border)] bg-white p-5 shadow-sm sm:p-6 ${className}`}>
            {(title || eyebrow || action) && (
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        {eyebrow && <p className="text-[10px] font-bold uppercase tracking-[0.15em] text-[var(--rr-accent)]">{eyebrow}</p>}
                        {title && <h2 className="mt-1 text-lg font-semibold text-[var(--rr-neutral-dark)]">{title}</h2>}
                    </div>
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}

export function EmptyState({ title, children }) {
    return (
        <div className="rounded-xl border border-dashed border-[var(--rr-border)] bg-[var(--rr-background)] px-5 py-10 text-center">
            <span aria-hidden="true" className="mx-auto flex size-10 items-center justify-center rounded-full bg-[var(--rr-accent-light)] text-lg text-[var(--rr-accent)]">✳</span>
            <h3 className="mt-3 text-sm font-semibold text-[var(--rr-neutral-dark)]">{title}</h3>
            {children && <p className="mx-auto mt-1 max-w-lg text-sm leading-6 text-[var(--rr-neutral-mid)]">{children}</p>}
        </div>
    );
}

export function Status({ children, tone = 'neutral' }) {
    const tones = {
        neutral: 'bg-[var(--rr-neutral-light)] text-[var(--rr-neutral-dark)]',
        success: 'bg-[var(--rr-accent-light)] text-[var(--rr-accent)]',
        warning: 'bg-amber-50 text-amber-900',
    };

    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${tones[tone]}`}>{children}</span>;
}

export function Definition({ label, value }) {
    return (
        <div className="min-w-0">
            <dt className="text-[10px] font-bold uppercase tracking-[0.13em] text-[var(--rr-neutral-mid)]">{label}</dt>
            <dd className="mt-1 break-words text-sm text-[var(--rr-neutral-dark)]">{value || 'Not provided'}</dd>
        </div>
    );
}

export function PageHeading({ eyebrow, title, children }) {
    return (
        <div className="mb-6">
            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">{eyebrow}</p>
            <h1 className="mt-2 text-2xl font-semibold tracking-tight text-[var(--rr-neutral-dark)] sm:text-3xl">{title}</h1>
            {children && <p className="mt-2 max-w-3xl text-sm leading-6 text-[var(--rr-neutral-mid)]">{children}</p>}
        </div>
    );
}

export function DashboardLink({ href, navigate, children, className = '' }) {
    return (
        <a href={href} onClick={(event) => {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            navigate(href);
        }} className={className}>
            {children}
        </a>
    );
}
