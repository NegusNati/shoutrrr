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
    /** Instance settings live inside the SPA — always client-side links. */
    spa: true;
};

export function instanceSettingsNavItems(): InstanceSettingsNavItem[] {
    return [
        {
            key: 'general',
            title: 'General',
            href: '/settings/instance',
            spa: true,
        },
        {
            key: 'polling',
            title: 'Polling',
            href: '/settings/instance/polling',
            spa: true,
        },
        {
            key: 'platforms',
            title: 'Platforms',
            href: '/settings/instance/platforms',
            spa: true,
        },
        {
            key: 'usage',
            title: 'Usage',
            href: '/settings/instance/usage',
            spa: true,
        },
        {
            key: 'admins',
            title: 'Admins',
            href: '/settings/instance/admins',
            spa: true,
        },
    ];
}
