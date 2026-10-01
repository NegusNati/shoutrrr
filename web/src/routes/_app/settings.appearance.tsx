import { createFileRoute } from '@tanstack/react-router';

import SettingsLayout from '@/layouts/settings-layout';
import AppearancePage from '@/pages/settings/appearance';

export const Route = createFileRoute('/_app/settings/appearance')({
    component: AppearanceRoute,
});

function AppearanceRoute() {
    return (
        <SettingsLayout>
            <AppearancePage />
        </SettingsLayout>
    );
}
