import { createFileRoute } from '@tanstack/react-router';

import ConnectLinkedIn from '@/pages/accounts/connect-linkedin';

export const Route = createFileRoute('/_app/accounts/connect/linkedin')({
    component: ConnectLinkedIn,
});
