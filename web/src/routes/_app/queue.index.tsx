import { createFileRoute } from '@tanstack/react-router';

import QueueIndexPage from '@/pages/queue/index';

export const Route = createFileRoute('/_app/queue/')({
    component: QueueIndexPage,
});
