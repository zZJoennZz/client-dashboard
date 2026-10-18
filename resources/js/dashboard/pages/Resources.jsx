import React from 'react';
import { Card, EmptyState, PageHeading, Status } from '../components/UI';

export default function Resources({ data }) {
    return (
        <div>
            <PageHeading eyebrow="Member library" title="Resources">
                {data.user.is_super_admin ? 'Provider access to resources across all businesses and publication states.' : 'Published member resources are shown here when available.'}
            </PageHeading>
            <Card>
                {data.resources.length === 0 ? (
                    <EmptyState title="No member resources available">Guides and other resources will appear here when they are published for members.</EmptyState>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {data.resources.map((resource) => (
                            <article key={resource.id} className="rounded-xl border border-[var(--rr-border)] bg-[var(--rr-background)] p-5">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <Status>{resource.resource_type}</Status>
                                    {resource.is_featured && <Status tone="success">Featured</Status>}
                                </div>
                                <h2 className="mt-4 font-semibold">{resource.title}</h2>
                                {resource.business_name && <p className="mt-1 text-xs font-medium text-[var(--rr-primary)]">{resource.business_name}</p>}
                                <p className="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{resource.description || 'No description provided.'}</p>
                                {resource.download_url ? <a className="mt-4 inline-flex min-h-10 items-center rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold text-[var(--rr-primary)] hover:bg-[var(--rr-neutral-light)]" href={resource.download_url}>Download {resource.file_name || 'resource'}</a> : resource.file_name && <p className="mt-4 text-xs text-[var(--rr-neutral-mid)]">Attachment: {resource.file_name}</p>}
                            </article>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}
