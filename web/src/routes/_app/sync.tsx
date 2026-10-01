import { createFileRoute } from '@tanstack/react-router';

import { syncQuery } from '@/features/sync/sync';
import SyncPipelinesPage from '@/pages/sync';

export const Route = createFileRoute('/_app/sync')({
    loader: ({ context: { queryClient } }) =>
        queryClient.ensureQueryData(syncQuery),
    component: SyncPipelinesPage,
});
