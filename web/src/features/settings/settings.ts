import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import type { Passkey } from '@/types/auth';

export type Connection = {
    provider: string;
    label: string;
    connected: boolean;
    id: string | null;
};

export type ConnectionsData = {
    connections: Connection[];
    hasPassword: boolean;
};

export type SecurityData = {
    canManageTwoFactor: boolean;
    canManagePasskeys: boolean;
    passkeys: Passkey[];
    passwordRules: string;
    twoFactorEnabled?: boolean;
    requiresConfirmation?: boolean;
};

/** key → per-channel toggles; channels the server marks always-on are excluded. */
export type PreferencesMatrix = Record<
    string,
    { in_app: boolean; mail: boolean }
>;

export type NotificationPreferencesData = {
    preferences: PreferencesMatrix;
    alwaysOn: string[];
};

export const settingsKeys = {
    security: ['settings', 'security'] as const,
    connections: ['settings', 'connections'] as const,
    notifications: ['settings', 'notifications'] as const,
};

export const securityQuery = queryOptions({
    queryKey: settingsKeys.security,
    queryFn: (): Promise<SecurityData> =>
        apiFetch<SecurityData>(endpoints.settingsSecurity),
});

export const connectionsQuery = queryOptions({
    queryKey: settingsKeys.connections,
    queryFn: (): Promise<ConnectionsData> =>
        apiFetch<ConnectionsData>(endpoints.settingsConnections),
});

export const notificationPreferencesQuery = queryOptions({
    queryKey: settingsKeys.notifications,
    queryFn: (): Promise<NotificationPreferencesData> =>
        apiFetch<NotificationPreferencesData>(endpoints.settingsNotifications),
});

/**
 * Fortify's two-factor + passkey endpoints live on the session (web) surface —
 * the SPA reaches them via webFetch with the same cookie + XSRF contract.
 */
export const fortifyRoutes = {
    twoFactorEnable: '/user/two-factor-authentication',
    twoFactorDisable: '/user/two-factor-authentication',
    twoFactorConfirm: '/user/confirmed-two-factor-authentication',
    twoFactorQrCode: '/user/two-factor-qr-code',
    twoFactorSecretKey: '/user/two-factor-secret-key',
    twoFactorRecoveryCodes: '/user/two-factor-recovery-codes',
    passkeyDestroy: (id: number) => `/user/passkeys/${id}`,
} as const;
