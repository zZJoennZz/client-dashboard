import React from 'react';
import { Card, EmptyState, PageHeading } from '../components/UI';

export default function Reports({ data }) {
    return (
        <div>
            <PageHeading eyebrow="Business insights" title="Reports">
                {data.user.is_super_admin ? 'Provider access to reports across all businesses, including unpublished records.' : 'Published reports shared with your business are listed here.'}
            </PageHeading>
            <Card>
                {data.reports.length === 0 ? (
                    <EmptyState title="No published reports yet">Your first report will appear here when it is published for your business.</EmptyState>
                ) : (
                    <div className="divide-y divide-[var(--rr-border)]">
                        {data.reports.map((report) => (
                            <article key={report.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 className="font-semibold">{report.title}</h2>
                                    {report.business_name && <p className="mt-1 text-xs font-medium text-[var(--rr-primary)]">{report.business_name}</p>}
                                    <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">{report.reporting_period || 'Period not specified'}{report.published_at ? ` · Published ${new Date(report.published_at).toLocaleDateString()}` : ''}</p>
                                    {report.description && <p className="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{report.description}</p>}
                                </div>
                                {report.download_url ? <a className="min-h-10 inline-flex items-center rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold text-[var(--rr-primary)] hover:bg-[var(--rr-neutral-light)]" href={report.download_url}>Download {report.file_name || 'report'}</a> : <span className="text-xs text-[var(--rr-neutral-mid)]">{report.file_name || 'No downloadable file attached'}</span>}
                            </article>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}
