
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Card, EmptyState, PageHeading } from '../components/UI';

export default function Reports({ data, onRefresh }) {
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState('');
    const [submitNotice, setSubmitNotice] = useState('');
    const [submissionOpen, setSubmissionOpen] = useState(false);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [submissionSearch, setSubmissionSearch] = useState('');
    const [periodStart, setPeriodStart] = useState('');
    const [periodEnd, setPeriodEnd] = useState('');
    const submissionTitleRef = useRef(null);
    const historyTitleRef = useRef(null);
    const historyTriggerRef = useRef(null);
    const historyWasOpenRef = useRef(false);

    useEffect(() => {
        if (!submissionOpen && !historyOpen) return undefined;

        function handleEscape(event) {
            if (event.key === 'Escape' && !submitting) {
                if (historyOpen) setHistoryOpen(false);
                else setSubmissionOpen(false);
            }
        }

        document.addEventListener('keydown', handleEscape);

        return () => document.removeEventListener('keydown', handleEscape);
    }, [submissionOpen, historyOpen, submitting]);

    useEffect(() => {
        if (historyOpen) {
            historyWasOpenRef.current = true;
            historyTitleRef.current?.focus();
        } else if (historyWasOpenRef.current) {
            historyWasOpenRef.current = false;
            historyTriggerRef.current?.focus();
        } else if (submissionOpen) submissionTitleRef.current?.focus();
    }, [submissionOpen, historyOpen]);

    useEffect(() => {
        if (!submissionOpen && !historyOpen) return undefined;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [submissionOpen, historyOpen]);

    const matchingSubmissions = useMemo(() => {
        const term = submissionSearch.trim().toLowerCase();
        const submissions = data.report_submissions || [];

        return submissions.filter((submission) => (
            !term || [
                submission.title,
                submission.description,
                submission.business_name,
                submission.status,
                submission.reporting_period,
                submission.file_name,
                submission.submitted_at,
            ].some((value) => String(value || '').toLowerCase().includes(term))
        ));
    }, [data.report_submissions, submissionSearch]);

    const reviewSubmissions = useMemo(
        () => (data.report_submissions || []).filter((submission) => (
            ['pending_review', 'rejected'].includes(submission.status)
        )),
        [data.report_submissions],
    );

    const submissionGroups = useMemo(() => {
        const groups = new Map();

        matchingSubmissions.forEach((submission) => {
            const { key, label } = submissionMonth(submission.submitted_at);
            if (!groups.has(key)) groups.set(key, { label, submissions: [] });
            groups.get(key).submissions.push(submission);
        });

        return [...groups.entries()].sort(([first], [second]) => second.localeCompare(first));
    }, [matchingSubmissions]);

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

    return (
        <div className="space-y-5">
            <PageHeading
                eyebrow="Business insights"
                title="Monthly Reports"
            >
                Review the reports you’ve submitted and track their status.
            </PageHeading>

            <div className="flex flex-wrap justify-end gap-3">
                <button
                    ref={historyTriggerRef}
                    type="button"
                    aria-haspopup="dialog"
                    aria-expanded={historyOpen}
                    onClick={() => setHistoryOpen(true)}
                    className="min-h-11 rounded-lg border border-[var(--rr-border)] bg-white px-4 text-sm font-semibold text-[var(--rr-neutral-dark)] hover:bg-[var(--rr-neutral-light)]"
                >
                    Pending / declined <span className="ml-1 rounded-full bg-[var(--rr-neutral-light)] px-2 py-0.5 text-xs">{reviewSubmissions.length}</span>
                </button>
                <button type="button" onClick={() => setSubmissionOpen(true)} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)]">
                    Submit a Report
                </button>
            </div>

            {submitNotice && <p role="status" className="rounded-lg bg-green-50 p-3 text-sm text-green-700">{submitNotice}</p>}

            <Card eyebrow="Submission archive" title="Reports you’ve submitted">
                <label className="block text-sm font-medium">
                    Search your submissions
                    <input
                        type="search"
                        value={submissionSearch}
                        onChange={(event) => setSubmissionSearch(event.target.value)}
                        placeholder="Search title, status, or description..."
                        className="mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-3 text-sm"
                    />
                </label>
                <p className="mt-3 text-xs text-[var(--rr-neutral-mid)]" aria-live="polite">
                    Showing {matchingSubmissions.length} of {(data.report_submissions || []).length} submissions
                </p>
                <div className="mt-4 space-y-3">
                    {submissionGroups.length === 0 ? (
                        <EmptyState title={data.report_submissions?.length ? 'No matching submissions' : 'No submissions yet'}>
                            {data.report_submissions?.length
                                ? 'Try another search term.'
                                : 'Reports you submit for review will appear here.'}
                        </EmptyState>
                    ) : submissionGroups.map(([month, group], index) => (
                        <details key={month} className="group/folder overflow-hidden rounded-xl border border-[var(--rr-border)] bg-white" open={index === 0}>
                            <summary className="flex min-h-14 cursor-pointer list-none items-center gap-3 px-4 py-3 hover:bg-[var(--rr-neutral-light)]">
                                <svg className="size-5 shrink-0 text-[var(--rr-accent)]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M3.75 6.75h6l2 2h8.5v9.5a1.5 1.5 0 0 1-1.5 1.5h-13a2 2 0 0 1-2-2v-11Z" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" />
                                    <path d="M3.75 9h16.5" stroke="currentColor" strokeWidth="1.5" />
                                </svg>
                                <span className="min-w-0 flex-1 truncate text-sm font-semibold">{group.label}</span>
                                <span className="rounded-full bg-[var(--rr-neutral-light)] px-2.5 py-1 text-xs font-semibold text-[var(--rr-neutral-mid)]">{group.submissions.length}</span>
                                <svg className="size-4 shrink-0 text-[var(--rr-neutral-mid)] transition-transform duration-200 group-open/folder:rotate-180" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m5 7.5 5 5 5-5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </summary>
                            <div className="divide-y divide-[var(--rr-border)] border-t border-[var(--rr-border)] px-4">
                                {group.submissions.map((submission) => (
                                    <details key={submission.id} className="group/submission py-3">
                                        <summary className="flex min-h-10 cursor-pointer list-none items-center gap-3 rounded-lg px-2 py-2 hover:bg-[var(--rr-neutral-light)]">
                                            <span className="min-w-0 flex-1">
                                                <span className="block break-words text-sm font-semibold">{submission.title}</span>
                                                <span className="mt-1 block text-xs text-[var(--rr-neutral-mid)]">
                                                    {submissionStatusLabel(submission.status)}
                                                    {submission.submitted_at ? ` · ${new Date(submission.submitted_at).toLocaleDateString()}` : ''}
                                                </span>
                                            </span>
                                            <span className="shrink-0 text-xs font-semibold text-[var(--rr-accent)]">View details</span>
                                            <svg className="size-4 shrink-0 text-[var(--rr-neutral-mid)] transition-transform duration-200 group-open/submission:rotate-180" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                <path d="m5 7.5 5 5 5-5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                            </svg>
                                        </summary>
                                        <div className="mt-2 rounded-lg bg-[var(--rr-background)] p-4">
                                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                                <div>
                                                    <dt className="text-xs font-medium text-[var(--rr-neutral-mid)]">Business</dt>
                                                    <dd className="mt-1">{submission.business_name || 'Not specified'}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs font-medium text-[var(--rr-neutral-mid)]">Status</dt>
                                                    <dd className="mt-1">{submissionStatusLabel(submission.status)}</dd>
                                                </div>
                                                {(submission.reporting_period_start || submission.reporting_period) && (
                                                    <div>
                                                        <dt className="text-xs font-medium text-[var(--rr-neutral-mid)]">Reporting period</dt>
                                                        <dd className="mt-1">
                                                            {submission.reporting_period_start && submission.reporting_period_end
                                                                ? `${submission.reporting_period_start} to ${submission.reporting_period_end}`
                                                                : submission.reporting_period}
                                                        </dd>
                                                    </div>
                                                )}
                                                {submission.submitted_at && (
                                                    <div>
                                                        <dt className="text-xs font-medium text-[var(--rr-neutral-mid)]">Submitted</dt>
                                                        <dd className="mt-1">{new Date(submission.submitted_at).toLocaleString()}</dd>
                                                    </div>
                                                )}
                                                {submission.file_name && (
                                                    <div className="sm:col-span-2">
                                                        <dt className="text-xs font-medium text-[var(--rr-neutral-mid)]">File</dt>
                                                        <dd className="mt-1 break-all">{submission.file_name}</dd>
                                                    </div>
                                                )}
                                            </dl>
                                            {submission.description && (
                                                <div className="mt-4 border-t border-[var(--rr-border)] pt-3">
                                                    <h4 className="text-xs font-medium text-[var(--rr-neutral-mid)]">Description</h4>
                                                    <p className="mt-1 whitespace-pre-line text-sm leading-6">{submission.description}</p>
                                                </div>
                                            )}
                                            {!submission.description && (
                                                <p className="mt-4 border-t border-[var(--rr-border)] pt-3 text-sm text-[var(--rr-neutral-mid)]">No description provided.</p>
                                            )}
                                            {submission.review_feedback && (
                                                <div className="mt-4 border-t border-[var(--rr-border)] pt-3">
                                                    <h4 className="text-xs font-medium text-[var(--rr-neutral-mid)]">Review feedback</h4>
                                                    <p className="mt-1 whitespace-pre-line text-sm leading-6">{submission.review_feedback}</p>
                                                </div>
                                            )}
                                            <a
                                                href={`/dashboard/reports/submissions/${submission.id}/download`}
                                                className="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg border border-[var(--rr-border)] bg-white px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)]"
                                            >
                                                Download report
                                            </a>
                                            {submission.ai_summary_url && (
                                                <a
                                                    href={submission.ai_summary_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    aria-label={`Open AI summary for ${submission.title} in a new tab`}
                                                    className="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-[var(--rr-primary)] px-3 text-xs font-semibold text-white hover:bg-[var(--rr-primary-light)] sm:ml-2"
                                                >
                                                    View AI summary
                                                </a>
                                            )}
                                        </div>
                                    </details>
                                ))}
                            </div>
                        </details>
                    ))}
                </div>
            </Card>

            {historyOpen && createPortal(
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="report-history-title"
                    className="dashboard-modal-backdrop fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/55 p-0 sm:items-center sm:p-5"
                    onClick={() => setHistoryOpen(false)}
                >
                    <section
                        className="dashboard-modal-panel max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-t-2xl bg-[var(--rr-background)] p-4 shadow-2xl sm:rounded-2xl sm:p-7"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--rr-accent)]">Report status</p>
                                <h2 id="report-history-title" ref={historyTitleRef} tabIndex={-1} className="mt-1 text-xl font-semibold">Pending or declined reports</h2>
                                <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">Reports still under review or declined by an administrator.</p>
                            </div>
                            <button
                                type="button"
                                aria-label="Close submission history"
                                onClick={() => setHistoryOpen(false)}
                                className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-[var(--rr-border)] text-lg hover:bg-white"
                            >
                                ×
                            </button>
                        </div>
                        <div className="mt-5 divide-y divide-[var(--rr-border)] overflow-hidden rounded-xl border border-[var(--rr-border)] bg-white px-4">
                            {reviewSubmissions.length === 0 ? (
                                <p className="py-6 text-sm text-[var(--rr-neutral-mid)]">There are no pending or declined reports.</p>
                            ) : reviewSubmissions.map((submission) => (
                                <article key={submission.id} className="flex flex-col gap-3 py-4 first:pt-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <h3 className="break-words text-sm font-semibold">{submission.title}</h3>
                                        <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">
                                            {submissionStatusLabel(submission.status)}
                                            {submission.submitted_at ? ` · ${new Date(submission.submitted_at).toLocaleDateString()}` : ''}
                                        </p>
                                        {submission.review_feedback && <p className="mt-2 text-sm leading-5">Feedback: {submission.review_feedback}</p>}
                                    </div>
                                    <a
                                        href={`/dashboard/reports/submissions/${submission.id}/download`}
                                        className="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-[var(--rr-border)] px-3 text-xs font-semibold hover:bg-[var(--rr-neutral-light)]"
                                    >
                                        Download
                                    </a>
                                </article>
                            ))}
                        </div>
                    </section>
                </div>,
                document.body,
            )}

            {submissionOpen && createPortal(
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="report-submission-title"
                    className="dashboard-modal-backdrop fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/55 p-0 sm:items-center sm:p-5"
                    onClick={() => { if (!submitting) setSubmissionOpen(false); }}
                >
                    <section className="dashboard-modal-panel max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-white p-5 shadow-2xl sm:rounded-2xl sm:p-7" onClick={(event) => event.stopPropagation()}>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase text-[var(--rr-accent)]">Member submission</p>
                                <h2 id="report-submission-title" ref={submissionTitleRef} tabIndex={-1} className="mt-1 text-xl font-semibold">Submit a report</h2>
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
                                <button type="submit" disabled={submitting} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white disabled:opacity-50">
                                    {submitting ? 'Submitting...' : 'Submit report'}
                                </button>
                            </div>
                        </form>
                    </section>
                </div>,
                document.body,
            )}

        </div>
    );
}

function submissionMonth(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})/);
    if (!match) return { key: '0000-00', label: 'Date not recorded' };

    const date = new Date(Number(match[1]), Number(match[2]) - 1, 1);

    return {
        key: `${match[1]}-${match[2]}`,
        label: new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(date),
    };
}

function submissionStatusLabel(status) {
    return {
        pending_review: 'Pending review',
        rejected: 'Declined',
    }[status] ?? String(status || 'Submission status').replaceAll('_', ' ');
}
