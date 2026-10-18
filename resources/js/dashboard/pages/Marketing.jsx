import React from 'react';
import { Card, EmptyState, PageHeading, Status } from '../components/UI';

export default function Marketing({ data }) {
    return (
        <div>
            <PageHeading eyebrow="Business growth" title="Marketing tools">
                {data.user.is_super_admin ? 'Provider-managed marketing activity across all member businesses.' : 'Scheduled and completed marketing activity recorded for your business.'}
            </PageHeading>
            <Card>
                {data.marketing.length === 0 ? (
                    <EmptyState title="No marketing activity has been shared">Completed and upcoming promotional activities shared with your business will appear here.</EmptyState>
                ) : (
                    <div className="divide-y divide-[var(--rr-border)]">
                        {data.marketing.map((item) => (
                            <article key={item.id} className="flex flex-col gap-3 py-4 first:pt-0 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 className="font-semibold">{item.title}</h2>
                                    {item.business_name && <p className="mt-1 text-xs font-medium text-[var(--rr-primary)]">{item.business_name}</p>}
                                    <p className="mt-1 text-sm leading-6 text-[var(--rr-neutral-mid)]">{item.member_description || (data.user.is_super_admin ? item.description : null) || 'No member-facing description provided.'}</p>
                                    {item.platform && <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Platform: {item.platform} · {item.promotion_type || 'Promotion'}</p>}
                                    {item.published_url && <a className="mt-2 inline-block text-xs font-semibold text-[var(--rr-primary)] underline" href={item.published_url} target="_blank" rel="noreferrer">View published content</a>}
                                    <p className="mt-2 text-xs text-[var(--rr-neutral-mid)]">{item.scheduled_at ? `Scheduled ${new Date(item.scheduled_at).toLocaleString()}` : 'No date provided'}</p>
                                </div>
                                <Status tone={item.status === 'completed' ? 'success' : 'neutral'}>{item.status}</Status>
                            </article>
                        ))}
                    </div>
                )}
                <p className="mt-5 border-t border-[var(--rr-border)] pt-4 text-xs leading-5 text-[var(--rr-neutral-mid)]">Any social media posting is performed manually. Automated posting and checkout are not connected.</p>
            </Card>
        </div>
    );
}
