import type { Account } from '@/types/compose';

export function canManageConnectedAccounts(permissions: string[]): boolean {
    return permissions.includes('workspace.accounts.manage');
}

export function shouldShowDashboardNoAccountsNotice(
    accounts: Account[],
    permissions: string[],
): boolean {
    return (
        accounts.length === 0 &&
        permissions.includes('workspace.read') &&
        !canManageConnectedAccounts(permissions)
    );
}
