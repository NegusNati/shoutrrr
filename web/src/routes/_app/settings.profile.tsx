import { createFileRoute } from '@tanstack/react-router';

import SettingsLayout from '@/layouts/settings-layout';
import ProfilePage from '@/pages/settings/profile';

export const Route = createFileRoute('/_app/settings/profile')({
    component: ProfileRoute,
});

function ProfileRoute() {
    return (
        <SettingsLayout>
            <ProfilePage />
        </SettingsLayout>
    );
}
