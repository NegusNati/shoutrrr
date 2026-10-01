import { createFileRoute } from '@tanstack/react-router';

import SettingsLayout from '@/layouts/settings-layout';
import ConnectionsPage from '@/pages/settings/connections';

export const Route = createFileRoute('/_app/settings/connections')({
    component: ConnectionsRoute,
});

function ConnectionsRoute() {
    return (
        <SettingsLayout>
            <ConnectionsPage />
        </SettingsLayout>
    );
}
