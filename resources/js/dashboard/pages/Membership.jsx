import React from 'react';
import { Card, EmptyState, PageHeading, Status } from '../components/UI';

export default function Membership({ data }) {
    const membership = data.membership;

    if (data.user.is_super_admin) {
        const businessesWithMemberships = data.businesses.filter((business) => business.membership);

        return (
            <div>
                <PageHeading eyebrow="Service provider" title="Memberships">
                    Membership plans and status across all businesses.
                </PageHeading>
                <Card>
                    {businessesWithMemberships.length === 0 ? (
                        <EmptyState title="No membership records found">Membership records will appear here as businesses join.</EmptyState>
                    ) : (
                        <div className="divide-y divide-[var(--rr-border)]">
                            {businessesWithMemberships.map((business) => (
                                <article key={business.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 className="font-semibold">{business.name}</h2>
                                        <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">{business.membership.plan || 'Membership plan'} · Started {business.membership.start_date || 'date not recorded'} · Renewal {business.membership.end_date || 'not set'}</p>
                                        <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Payment: {business.membership.payment_is_simulated ? 'simulated for testing; no payment collected' : business.membership.payment_status || 'not recorded'}</p>
                                    </div>
                                    <Status tone={business.membership.status === 'active' ? 'success' : 'neutral'}>{business.membership.status}</Status>
                                </article>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        );
    }

    return (
        <div>
            <PageHeading eyebrow="Your account" title="Membership">
                Membership details shown here are read from your business membership record.
            </PageHeading>
            {!membership ? (
                <Card><EmptyState title="No membership record found">There is currently no membership associated with your active business account.</EmptyState></Card>
            ) : (
                <div className="max-w-3xl">
                    <Card eyebrow="The Argyle Buzz" title={membership.plan || 'Membership plan'}>
                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <Status tone={membership.status === 'active' ? 'success' : 'neutral'}>{membership.status}</Status>
                            <span className="text-sm text-[var(--rr-neutral-mid)]">Started {membership.start_date || 'date not recorded'}</span>
                            <span className="text-sm text-[var(--rr-neutral-mid)]">Renewal {membership.end_date || 'not set'}</span>
                        </div>
                        <div className="mt-6 grid gap-5 border-t border-[var(--rr-border)] pt-5 sm:grid-cols-2">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.13em] text-[var(--rr-neutral-mid)]">Recorded payment state</p>
                                <p className="mt-1 text-sm font-medium">{membership.payment_is_simulated ? 'Simulated for testing' : membership.payment_status || 'Not recorded'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.13em] text-[var(--rr-neutral-mid)]">Payment processing</p>
                                <p className="mt-1 text-sm font-medium">Not connected</p>
                            </div>
                        </div>
                        <p className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                            {membership.payment_is_simulated
                                ? 'This registration used a development placeholder. The membership record is marked active, but no payment was collected.'
                                : 'This dashboard does not process or verify payments. The payment state above reflects the current database record only.'}
                        </p>
                    </Card>
                </div>
            )}
        </div>
    );
}
