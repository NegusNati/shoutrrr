import { createFileRoute } from '@tanstack/react-router';

import Connections from '@/pages/settings/connections';

export const Route = createFileRoute('/_app/settings/connections')({
    component: Connections,
});
