import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

const root = document.getElementById('member-dashboard');

if (root) {
    createRoot(root).render(
        <React.StrictMode>
            <App dataUrl={root.dataset.dashboardDataUrl} membersUrl={root.dataset.membersUrl} loginUrl={root.dataset.loginUrl} />
        </React.StrictMode>,
    );
}
