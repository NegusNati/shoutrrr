export type InstanceSettingsNavKey =
    | 'general'
    | 'polling'
    | 'platforms'
    | 'usage'
    | 'admins';

export type InstanceSettingsNavItem = {
    key: InstanceSettingsNavKey;
    title: string;
    href: string;
};

/**
 * Instance settings live in the standalone SPA (/app/*) — these are plain
 * string URLs, not Inertia routes.
 */
export function instanceSettingsNavItems(): InstanceSettingsNavItem[] {
    return [
        {
            key: 'general',
            title: 'General',
            href: '/app/settings/instance',
        },
        {
            key: 'polling',
            title: 'Polling',
            href: '/app/settings/instance/polling',
        },
        {
            key: 'platforms',
            title: 'Platforms',
            href: '/app/settings/instance/platforms',
        },
        {
            key: 'usage',
            title: 'Usage',
            href: '/app/settings/instance/usage',
        },
        {
            key: 'admins',
            title: 'Admins',
            href: '/app/settings/instance/admins',
        },
    ];
}
