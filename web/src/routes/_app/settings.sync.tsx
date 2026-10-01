import { createFileRoute } from '@tanstack/react-router';

import SyncPipelinesPage from '@/pages/settings/sync';

export const Route = createFileRoute('/_app/settings/sync')({
    component: SyncPipelinesPage,
});
