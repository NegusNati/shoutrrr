import type { Href } from '@/lib/href';

export type WorkspaceSettingsNavKey =
    | 'overview'
    | 'members'
    | 'apiKeys'
    | 'subscription';

export type WorkspaceSettingsNavItem = {
    key: WorkspaceSettingsNavKey;
    title: string;
    href: Href;
};

export function workspaceSettingsNavItems({
    permissions,
    billingEnabled,
}: {
    permissions: string[];
    billingEnabled: boolean;
}): WorkspaceSettingsNavItem[] {
    const canManageSettings = permissions.includes('workspace.settings.manage');
    const canManageBilling = permissions.includes('workspace.billing.manage');

    const items: WorkspaceSettingsNavItem[] = [
        {
            key: 'overview',
            title: 'Overview',
            href: '/settings/workspace',
        },
        {
            key: 'members',
            title: 'Members',
            href: '/settings/workspace/members',
        },
    ];

    if (canManageSettings) {
        items.push({
            key: 'apiKeys',
            title: 'API keys',
            href: '/settings/workspace/api-keys',
        });
    }

    if (billingEnabled && canManageBilling) {
        items.push({
            key: 'subscription',
            title: 'Subscription',
            href: '/settings/workspace/subscription',
        });
    }

    return items;
}
