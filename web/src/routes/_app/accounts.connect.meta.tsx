import { createFileRoute } from '@tanstack/react-router';

import ConnectMeta from '@/pages/accounts/connect-meta';

export const Route = createFileRoute('/_app/accounts/connect/meta')({
    component: ConnectMeta,
});
