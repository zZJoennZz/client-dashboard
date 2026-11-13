<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#F9FAFB">
    <title>{{ $report->title }} · AI report summary</title>
    @vite(['resources/css/app.css', 'resources/js/report-ai-summary.js'])
    <style>
        .ai-summary { display: grid; gap: 1.25rem; }
        .ai-summary > p { font-size: 1.05rem; line-height: 1.75; }
        .ai-block { overflow: hidden; border: 1px solid var(--rr-border); border-radius: 1rem; padding: 1.25rem; }
        .ai-block h3 { margin-bottom: 1rem; font-size: 1.1rem; font-weight: 700; }
        
        /* Responsive Table Wrapper */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .ai-block table { width: 100%; border-collapse: collapse; font-size: .875rem; white-space: nowrap; }
        .ai-block th, .ai-block td { border-bottom: 1px solid var(--rr-border); padding: .65rem .75rem; text-align: left; vertical-align: top; }
        .ai-block th { background: var(--rr-neutral-light); font-weight: 600; }
        .ai-block ul { display: grid; gap: .75rem; padding-left: 1.25rem; list-style: disc; }
        .ai-block .note, .ai-block .source, .ai-summary > .note { margin-top: 1rem; color: var(--rr-neutral-mid); font-size: .8rem; line-height: 1.5; }
        .ai-chart { position: relative; min-height: 18rem; }
        .ai-chart canvas { max-height: 26rem; }
        .ai-summary details { border-top: 1px solid var(--rr-border); padding-top: 1rem; }
        .ai-summary details summary { cursor: pointer; font-weight: 600; }
        @media print {
            .no-print { display: none !important; }
            .ai-block { break-inside: avoid; }
            .table-responsive { overflow: visible !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-[var(--rr-neutral-light)] font-sans text-[var(--rr-neutral-dark)] antialiased">
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-8 sm:py-12">
        <a href="{{ route('dashboard') }}" class="no-print inline-flex min-h-10 items-center rounded-lg border border-[var(--rr-border)] bg-white px-3 text-sm font-semibold hover:bg-[var(--rr-neutral-light)]">
            Back to dashboard
        </a>

        <header class="my-6 rounded-2xl bg-white p-5 shadow-sm sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--rr-accent)]">AI report summary</p>
            <h1 class="mt-2 break-words text-2xl font-bold sm:text-3xl">{{ $report->title }}</h1>
            <p class="mt-2 text-sm text-[var(--rr-neutral-mid)]">
                {{ $report->business?->company_name ?? 'Business report' }}
                @if($report->reporting_period) · {{ $report->reporting_period }} @endif
            </p>
            @if($generatedAt)
                <p class="mt-1 text-xs text-[var(--rr-neutral-mid)]">Summary generated {{ $generatedAt->format('F j, Y g:i A') }}</p>
            @endif
        </header>

        <article class="rounded-2xl border border-[var(--rr-border)] bg-white p-5 shadow-sm sm:p-8">
            @include('reports.ai-summary', ['analysis' => $analysis])
        </article>
    </main>

    <script>
        // Automatically wrap AI-generated tables in a responsive scroll container
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.ai-block table').forEach(function (table) {
                if (!table.parentElement.classList.contains('table-responsive')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'table-responsive';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });
        });
    </script>
</body>
</html>