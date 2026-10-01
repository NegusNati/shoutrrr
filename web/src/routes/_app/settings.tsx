import { createFileRoute, Outlet, useLocation } from '@tanstack/react-router';

import SettingsLayout from '@/layouts/settings-layout';

export const Route = createFileRoute('/_app/settings')({
    component: SettingsSection,
});

/**
 * `/settings/workspace/*` and `/settings/instance/*` have their own layouts
 * (sidebar sections, no inner side-nav), so the user-settings nav wraps only
 * the user-facing pages.
 */
function SettingsSection() {
    const pathname = useLocation({ select: (l) => l.pathname });

    if (
        pathname.startsWith('/app/settings/workspace') ||
        pathname.startsWith('/app/settings/instance')
    ) {
        return <Outlet />;
    }

    return (
        <SettingsLayout>
            <Outlet />
        </SettingsLayout>
    );
}
