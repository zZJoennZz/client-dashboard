
import React, { useMemo, useState } from 'react';
import { Card, EmptyState, PageHeading } from '../components/UI';

export default function Reports({ data }) {
    const [search, setSearch] = useState('');
    const [period, setPeriod] = useState('all');
    const [expanded, setExpanded] = useState([]);

    const periods = [
        ...new Set(
            data.reports
                .map((report) => report.reporting_period)
                .filter(Boolean)
        ),
    ].sort((a, b) => b.localeCompare(a));

    const reports = useMemo(() => {
        const term = search.trim().toLowerCase();

        return data.reports.filter((report) => {
            const matchesSearch =
                !term ||
                [
                    report.title,
                    report.description,
                    report.reporting_period,
                    report.file_name,
                ].some((value) =>
                    String(value || '').toLowerCase().includes(term)
                );

            return (
                matchesSearch &&
                (period === 'all' ||
                    report.reporting_period === period)
            );
        });
    }, [data.reports, search, period]);

    function toggleDetails(id) {
        setExpanded((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id]
        );
    }

    return (
        <div className="space-y-5">
            <PageHeading
                eyebrow="Business insights"
                title="Monthly Reports"
            >
                Browse monthly reports and historical records
                shared with your business.
            </PageHeading>

            <Card>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="text-sm font-medium">
                        Search reports
                        <input
                            type="search"
                            value={search}
                            onChange={(event) =>
                                setSearch(event.target.value)
                            }
                            placeholder="Search titles or keywords..."
                            className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm"
                        />
                    </label>

                    <label className="text-sm font-medium">
                        Reporting period
                        <select
                            value={period}
                            onChange={(event) =>
                                setPeriod(event.target.value)
                            }
                            className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 text-sm"
                        >
                            <option value="all">All periods</option>
                            {periods.map((item) => (
                                <option key={item} value={item}>
                                    {item}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <p
                    className="mt-3 text-xs text-[var(--rr-neutral-mid)]"
                    aria-live="polite"
                >
                    Showing {reports.length} of {data.reports.length} reports
                </p>
            </Card>

            <Card>
                {reports.length === 0 ? (
                    <EmptyState title="No reports found">
                        {data.reports.length
                            ? 'Try changing your search or reporting period.'
                            : 'Reports will appear here when published for your business.'}
                    </EmptyState>
                ) : (
                    <div className="divide-y divide-[var(--rr-border)]">
                        {reports.map((report) => {
                            const isExpanded = expanded.includes(report.id);

                            return (
                                <article
                                    key={report.id}
                                    className="py-4 first:pt-0 last:pb-0"
                                >
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h2 className="font-semibold">
                                                {report.title}
                                            </h2>

                                            <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">
                                                {report.reporting_period ||
                                                    'Period not specified'}
                                                {report.published_at &&
                                                    ` · Published ${new Date(
                                                        report.published_at
                                                    ).toLocaleDateString()}`}
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggleDetails(report.id)
                                                }
                                                aria-expanded={isExpanded}
                                                className="min-h-10 rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold"
                                            >
                                                {isExpanded
                                                    ? 'Hide details'
                                                    : 'View details'}
                                            </button>

                                            {report.download_url && (
                                                <a
                                                    href={report.download_url}
                                                    className="inline-flex min-h-10 items-center rounded-lg bg-[var(--rr-primary)] px-3 text-xs font-semibold text-white"
                                                >
                                                    Download report
                                                </a>
                                            )}
                                        </div>
                                    </div>

                                    {isExpanded && (
                                        <div className="mt-3 rounded-lg bg-[var(--rr-background)] p-4">
                                            <p className="whitespace-pre-line text-sm leading-6 text-[var(--rr-neutral-mid)]">
                                                {report.description ||
                                                    'No additional description provided.'}
                                            </p>

                                            {report.file_name && (
                                                <p className="mt-2 text-xs text-[var(--rr-neutral-mid)]">
                                                    File: {report.file_name}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </article>
                            );
                        })}
                    </div>
                )}
            </Card>
        </div>
    );
}
