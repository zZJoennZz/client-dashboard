import React, { useEffect, useState } from 'react';
import DashboardLayout from './layouts/DashboardLayout';
import Overview from './pages/Overview';
import BusinessProfile from './pages/BusinessProfile';
import Membership from './pages/Membership';
import Reports from './pages/Reports';
import Events from './pages/Events';
import Resources from './pages/Resources';
import Marketing from './pages/Marketing';
import Settings from './pages/Settings';

const pages = {
    overview: { title: 'Overview', component: Overview },
    business: { title: 'Business profile', component: BusinessProfile },
    membership: { title: 'Membership', component: Membership },
    reports: { title: 'Reports', component: Reports },
    events: { title: 'Events', component: Events },
    resources: { title: 'Resources', component: Resources },
    marketing: { title: 'Marketing tools', component: Marketing },
    settings: { title: 'Settings', component: Settings },
};

function pageFromPath(pathname) {
    const segment = pathname.replace(/^\/dashboard\/?/, '').split('/')[0];

    if (!segment) return 'overview';
    return Object.hasOwn(pages, segment) ? segment : null;
}

export default function App({ dataUrl, loginUrl }) {
    const [pathname, setPathname] = useState(window.location.pathname);
    const [data, setData] = useState(null);
    const [loadState, setLoadState] = useState('loading');
    const [menuOpen, setMenuOpen] = useState(false);

    async function refreshData() {
        const response = await fetch(dataUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error(`Dashboard data could not be refreshed (HTTP ${response.status}).`);
        setData(await response.json());
    }

    useEffect(() => {
        const handlePopState = () => setPathname(window.location.pathname);
        window.addEventListener('popstate', handlePopState);

        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    useEffect(() => {
        const controller = new AbortController();

        async function loadDashboard() {
            setLoadState('loading');

            try {
                const response = await fetch(dataUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });

                if (response.status === 401 || response.redirected) {
                    window.location.assign(loginUrl);
                    return;
                }

                if (!response.ok) {
                    throw new Error(`Dashboard data could not be loaded (HTTP ${response.status}).`);
                }

                setData(await response.json());
                setLoadState('ready');
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setLoadState('error');
                }
            }
        }

        loadDashboard();

        return () => controller.abort();
    }, [dataUrl, loginUrl]);

    function navigate(to) {
        if (to === window.location.pathname) {
            setMenuOpen(false);
            return;
        }

        window.history.pushState({}, '', to);
        setPathname(to);
        setMenuOpen(false);
        window.scrollTo({ top: 0, behavior: 'instant' });
    }

    const pageKey = pageFromPath(pathname);
    const page = pageKey ? pages[pageKey] : null;
    const Page = page?.component;

    return (
        <DashboardLayout
            data={data}
            pageTitle={page?.title ?? 'Page not found'}
            pageKey={pageKey}
            menuOpen={menuOpen}
            setMenuOpen={setMenuOpen}
            navigate={navigate}
        >
            {loadState === 'loading' && <LoadingState />}
            {loadState === 'error' && <ErrorState onRetry={() => window.location.reload()} />}
            {loadState === 'ready' && (Page
                ? <Page data={data} navigate={navigate} onRefresh={refreshData} />
                : <NotFound navigate={navigate} />)}
        </DashboardLayout>
    );
}

function LoadingState() {
    return (
        <div aria-label="Loading dashboard" aria-live="polite" className="space-y-5">
            <div className="h-40 animate-pulse rounded-2xl bg-white shadow-sm" />
            <div className="grid gap-5 md:grid-cols-2">
                <div className="h-64 animate-pulse rounded-2xl bg-white shadow-sm" />
                <div className="h-64 animate-pulse rounded-2xl bg-white shadow-sm" />
            </div>
        </div>
    );
}

function ErrorState({ onRetry }) {
    return (
        <div role="alert" className="rounded-2xl border border-red-200 bg-white p-8">
            <h2 className="text-lg font-semibold text-[var(--rr-error)]">Dashboard data is unavailable</h2>
            <p className="mt-2 text-sm text-[var(--rr-neutral-mid)]">We couldn't load your member information. Please try again.</p>
            <button onClick={onRetry} className="mt-5 min-h-11 rounded-lg bg-[var(--rr-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">
                Try again
            </button>
        </div>
    );
}

function NotFound({ navigate }) {
    return (
        <div className="rounded-2xl border border-[var(--rr-border)] bg-white px-6 py-16 text-center">
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">Member workspace</p>
            <h2 className="mt-3 text-2xl font-semibold">That dashboard page isn't available.</h2>
            <p className="mt-2 text-sm text-[var(--rr-neutral-mid)]">The link may be out of date, or the page may not be part of your workspace yet.</p>
            <button onClick={() => navigate('/dashboard')} className="mt-6 min-h-11 rounded-lg bg-[var(--rr-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)]">
                Return to overview
            </button>
        </div>
    );
}
