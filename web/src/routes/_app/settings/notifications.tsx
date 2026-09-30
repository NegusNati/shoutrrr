import { createFileRoute } from '@tanstack/react-router';

import Notifications from '@/pages/settings/notifications';

export const Route = createFileRoute('/_app/settings/notifications')({
    component: Notifications,
});
