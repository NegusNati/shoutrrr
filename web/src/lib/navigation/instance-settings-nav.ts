import type { Href } from '@/lib/href';

export type InstanceSettingsNavKey =
    | 'general'
    | 'polling'
    | 'platforms'
    | 'usage'
    | 'admins';

export type InstanceSettingsNavItem = {
    key: InstanceSettingsNavKey;
    title: string;
    href: Href;
};

export function instanceSettingsNavItems(): InstanceSettingsNavItem[] {
    return [
        {
            key: 'general',
            title: 'General',
            href: '/settings/instance',
        },
        {
            key: 'polling',
            title: 'Polling',
            href: '/settings/instance/polling',
        },
        {
            key: 'platforms',
            title: 'Platforms',
            href: '/settings/instance/platforms',
        },
        {
            key: 'usage',
            title: 'Usage',
            href: '/settings/instance/usage',
        },
        {
            key: 'admins',
            title: 'Admins',
            href: '/settings/instance/admins',
        },
    ];
}
