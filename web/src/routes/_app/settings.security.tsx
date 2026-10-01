import { createFileRoute, redirect } from '@tanstack/react-router';

import { confirmedPasswordStatus } from '@/features/user-settings/user-settings';
import SettingsLayout from '@/layouts/settings-layout';
import SecurityPage from '@/pages/settings/security';

export const Route = createFileRoute('/_app/settings/security')({
    // Mirrors the legacy `RequirePassword` middleware on settings/security:
    // Fortify's two-factor + passkey endpoints answer 423 once
    // `auth.password_confirmed_at` has gone stale, so gate the page itself.
    beforeLoad: async () => {
        const { confirmed } = await confirmedPasswordStatus().catch(() => ({
            confirmed: true,
        }));

        if (!confirmed) {
            throw redirect({
                to: '/confirm-password',
                search: { redirect: '/settings/security' },
            });
        }
    },
    component: SecurityRoute,
});

function SecurityRoute() {
    return (
        <SettingsLayout>
            <SecurityPage />
        </SettingsLayout>
    );
}
