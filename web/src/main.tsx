import { QueryClientProvider } from '@tanstack/react-query';
import { createRouter, RouterProvider } from '@tanstack/react-router';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import {
    installDiagnostics,
    recordNavigation,
} from '@/lib/diagnostics-collector';
import { queryClient } from '@/lib/query-client';
import ErrorPage from '@/pages/error';

import { routeTree } from './routeTree.gen';

import './styles/app.css';

// Start capturing console/network breadcrumbs as early as possible so the
// feedback widget can attach the events leading up to a report.
installDiagnostics();

const router = createRouter({
    routeTree,
    basepath: '/app',
    defaultPreload: 'intent',
    defaultNotFoundComponent: () => <ErrorPage status={404} />,
    defaultErrorComponent: () => <ErrorPage status={500} />,
    context: {
        queryClient,
    },
});

// Record SPA navigations into the diagnostics breadcrumbs.
router.subscribe('onResolved', (event) => {
    recordNavigation(event.toLocation.href);
});

declare module '@tanstack/react-router' {
    interface Register {
        router: typeof router;
    }
}

createRoot(document.getElementById('spa-root')!).render(
    <StrictMode>
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
        </QueryClientProvider>
    </StrictMode>,
);
