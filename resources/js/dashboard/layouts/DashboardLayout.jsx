import React from 'react';
import Sidebar from '../components/Sidebar';
import Topbar from '../components/Topbar';

export default function DashboardLayout({ data, pageTitle, pageKey, menuOpen, setMenuOpen, navigate, children }) {
    return (
        <div className="min-h-screen bg-[var(--rr-background)]">
            <Sidebar
                pageKey={pageKey}
                menuOpen={menuOpen}
                setMenuOpen={setMenuOpen}
                navigate={navigate}
            />
            <div className="min-h-screen lg:pl-72">
                <Topbar
                    data={data}
                    pageTitle={pageTitle}
                    setMenuOpen={setMenuOpen}
                />
                <main id="dashboard-main" className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-10">
                    {children}
                </main>
            </div>
        </div>
    );
}
