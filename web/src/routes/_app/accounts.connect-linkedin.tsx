import { createFileRoute } from '@tanstack/react-router';

import ConnectLinkedInPage from '@/pages/accounts/connect-linkedin';

export const Route = createFileRoute('/_app/accounts/connect-linkedin')({
    component: ConnectLinkedInPage,
});
