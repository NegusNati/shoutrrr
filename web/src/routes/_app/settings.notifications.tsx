import { createFileRoute } from '@tanstack/react-router';

import SettingsLayout from '@/layouts/settings-layout';
import NotificationsPage from '@/pages/settings/notifications';

export const Route = createFileRoute('/_app/settings/notifications')({
    component: NotificationsRoute,
});

function NotificationsRoute() {
    return (
        <SettingsLayout>
            <NotificationsPage />
        </SettingsLayout>
    );
}
