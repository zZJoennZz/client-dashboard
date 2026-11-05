
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Card, EmptyState, PageHeading } from '../components/UI';

export default function Reports({ data, onRefresh }) {
    const [search, setSearch] = useState('');
    const [period, setPeriod] = useState('all');
    const [expanded, setExpanded] = useState([]);
    const trackedReportIds = useRef(new Set());
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState('');
    const [submitNotice, setSubmitNotice] = useState('');
    const [submissionOpen, setSubmissionOpen] = useState(false);
    const [periodStart, setPeriodStart] = useState('');
    const [periodEnd, setPeriodEnd] = useState('');
    const submitButtonRef = useRef(null);

    useEffect(() => {
        if (!submissionOpen) return undefined;

        function handleEscape(event) {
            if (event.key === 'Escape' && !submitting) setSubmissionOpen(false);
        }

        document.addEventListener('keydown', handleEscape);
        submitButtonRef.current?.focus();

        return () => document.removeEventListener('keydown', handleEscape);
    }, [submissionOpen, submitting]);

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

    async function submitReport(event) {
        event.preventDefault();
        setSubmitting(true);
        setSubmitError('');
        setSubmitNotice('');

        const form = event.currentTarget;
        const body = new FormData(form);

        try {
            const response = await fetch('/dashboard/reports/submissions', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body,
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(
                    result.message ||
                    Object.values(result.errors || {}).flat()[0] ||
                    'The report could not be submitted.'
                );
            }
            form.reset();
            setSubmitNotice('Report submitted for administrative review.');
            await onRefresh();
            setSubmissionOpen(false);
            setPeriodStart('');
            setPeriodEnd('');
        } catch (error) {
            setSubmitError(error.message);
        } finally {
            setSubmitting(false);
        }
    }

    function toggleDetails(id) {
        const isOpening = !expanded.includes(id);

        setExpanded((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id]
        );

        if (isOpening && !trackedReportIds.current.has(id)) {
            trackedReportIds.current.add(id);
            fetch(`/dashboard/activity/reports/${id}/viewed`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content ?? '',
                },
            }).catch(() => {});
        }

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

            <div className="flex justify-end">
                <button type="button" onClick={() => setSubmissionOpen(true)} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white">
                    Submit a Report
                </button>
            </div>

            {submitNotice && <p role="status" className="rounded-lg bg-green-50 p-3 text-sm text-green-700">{submitNotice}</p>}

            {submissionOpen && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="report-submission-title"
                    className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-5"
                    onClick={() => { if (!submitting) setSubmissionOpen(false); }}
                >
                    <section className="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-xl bg-white p-5 sm:rounded-xl sm:p-7" onClick={(event) => event.stopPropagation()}>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase text-[var(--rr-accent)]">Member submission</p>
                                <h2 id="report-submission-title" className="mt-1 text-xl font-semibold">Submit a report</h2>
                            </div>
                            <button type="button" aria-label="Close report submission" disabled={submitting} onClick={() => setSubmissionOpen(false)} className="min-h-10 min-w-10 rounded-lg border border-[var(--rr-border)] disabled:opacity-50">×</button>
                        </div>
                        <p className="mt-3 mb-4 text-sm text-[var(--rr-neutral-mid)]">
                            Accepted formats: PDF, CSV, XLS, XLSX, DOC, DOCX, PPT, PPTX, and TXT (maximum 20 MB).
                        </p>
                        {submitError && <p role="alert" className="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{submitError}</p>}
                        <form onSubmit={submitReport} className="grid gap-4 md:grid-cols-2">
                            <div className="md:col-span-2 rounded-lg bg-[var(--rr-background)] px-3 py-2 text-sm">
                                <span className="font-medium">Business:</span>{' '}
                                {data.business?.company_name || data.business?.name || 'No associated active business'}
                            </div>
                            <label className="text-sm font-medium">
                                Start Date
                                <input type="date" name="reporting_period_start" required value={periodStart} onChange={(event) => setPeriodStart(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                            </label>
                            <label className="text-sm font-medium">
                                End Date
                                <input type="date" name="reporting_period_end" required min={periodStart || undefined} value={periodEnd} onChange={(event) => setPeriodEnd(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                            </label>
                            <label className="text-sm font-medium md:col-span-2">
                                Report title
                                <input name="title" required maxLength="255" className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] px-3 text-sm" />
                            </label>
                            <label className="text-sm font-medium md:col-span-2">
                                Description or summary
                                <textarea name="description" rows="4" maxLength="10000" className="mt-1 w-full rounded-lg border border-[var(--rr-border)] px-3 py-2 text-sm" />
                            </label>
                            <label className="text-sm font-medium md:col-span-2">
                                Report file
                                <input name="file" type="file" required accept=".pdf,.csv,.xls,.xlsx,.doc,.docx,.ppt,.pptx,.txt" className="mt-1 w-full text-sm" />
                            </label>
                            <div className="md:col-span-2 flex justify-end">
                                <button ref={submitButtonRef} type="submit" disabled={submitting} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white disabled:opacity-50">
                                    {submitting ? 'Submitting...' : 'Submit report'}
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            )}

            <Card eyebrow="Your submissions" title="Submission history">
                {(data.report_submissions || []).length === 0 ? (
                    <p className="text-sm text-[var(--rr-neutral-mid)]">No reports are awaiting review or have been rejected.</p>
                ) : (
                    <div className="divide-y divide-[var(--rr-border)]">
                        {(data.report_submissions || []).map((submission) => (
                            <div key={submission.id} className="grid gap-2 py-4 first:pt-0 last:pb-0 sm:grid-cols-[1fr_auto]">
                                <div>
                                    <p className="font-semibold">{submission.title}</p>
                                    <p className="text-sm text-[var(--rr-neutral-mid)]">{submission.business_name} · {submission.status.replace('_', ' ')}</p>
                                    {(submission.reporting_period_start || submission.reporting_period) && <p className="mt-1 text-sm">{submission.reporting_period_start && submission.reporting_period_end ? `${submission.reporting_period_start} to ${submission.reporting_period_end}` : submission.reporting_period}</p>}
                                    {submission.review_feedback && <p className="mt-2 text-sm">Feedback: {submission.review_feedback}</p>}
                                </div>
                                <a href={`/dashboard/reports/submissions/${submission.id}/download`} className="min-h-10 rounded-lg border border-[var(--rr-border)] px-3 py-2 text-center text-xs font-semibold">Download</a>
                            </div>
                        ))}
                    </div>
                )}
            </Card>

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
