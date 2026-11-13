import React from 'react';
import Sidebar from '../components/Sidebar';
import Topbar from '../components/Topbar';

export default function DashboardLayout({ data, pageTitle, pageKey, modulePermissions, menuOpen, setMenuOpen, navigate, children }) {
    return (
        <div className="min-h-screen min-w-0 bg-[var(--rr-background)]">
            <Sidebar
                pageKey={pageKey}
                modulePermissions={modulePermissions}
                menuOpen={menuOpen}
                setMenuOpen={setMenuOpen}
                navigate={navigate}
            />
            <div className="min-h-screen min-w-0 lg:pl-72">
                <Topbar
                    data={data}
                    pageTitle={pageTitle}
                    setMenuOpen={setMenuOpen}
                />
                <main id="dashboard-main" className="mx-auto min-h-[calc(100vh-4rem)] w-full max-w-7xl min-w-0 px-4 py-6 sm:px-6 sm:py-8 lg:px-10">
                    {children}
                </main>
            </div>
        </div>
    );
}
