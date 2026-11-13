import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

const root = document.getElementById('super-admin');

if (root) {
    createRoot(root).render(
        <React.StrictMode>
            <App
                overviewUrl={root.dataset.overviewUrl}
                settingsUrl={root.dataset.settingsUrl}
                settingsUpdateUrl={root.dataset.settingsUpdateUrl}
                engagementUrl={root.dataset.engagementUrl}
                businessesUrl={root.dataset.businessesUrl}
                accountsUrl={root.dataset.accountsUrl}
                workspaceUrl={root.dataset.workspaceUrl}
                membershipsUrl={root.dataset.membershipsUrl}
                resourcesUrl={root.dataset.resourcesUrl}
                reportsUrl={root.dataset.reportsUrl}
                eventsUrl={root.dataset.eventsUrl}
                marketingUrl={root.dataset.marketingUrl}
                servicesUrl={root.dataset.servicesUrl}
                ordersUrl={root.dataset.ordersUrl}
                announcementsUrl={root.dataset.announcementsUrl}
                exportUrl={root.dataset.exportUrl}
                loginUrl={root.dataset.loginUrl}
                moduleAccess={JSON.parse(root.dataset.moduleAccess || '{}')}
                roleLabel={root.dataset.roleLabel}
                canManageSystemRoles={root.dataset.canManageSystemRoles === 'true'}
            />
        </React.StrictMode>,
    );
}
