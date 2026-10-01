import { queryOptions } from '@tanstack/react-query';

import { apiFetch, apiUpload, webFetch, webPost } from '@/lib/api';
import type { Passkey } from '@/types/auth';

export type ProfileData = {
    mustVerifyEmail: boolean;
};

export const profileQuery = queryOptions({
    queryKey: ['user-settings', 'profile'],
    queryFn: () => apiFetch<ProfileData>('settings/profile'),
});

/**
 * PUT with a Laravel `_method` spoof so the avatar can ride in multipart
 * FormData alongside the text fields — same trick Inertia's Form uses.
 */
export const updateProfile = (data: {
    name: string;
    email: string;
    photo: File | null;
}) => {
    const form = new FormData();
    form.set('_method', 'PUT');
    form.set('name', data.name);
    form.set('email', data.email);
    if (data.photo) {
        form.set('photo', data.photo);
    }

    return apiUpload<{ message: string }>('settings/profile', form);
};

export const deleteAccount = (password: string) =>
    apiFetch<{ message: string }>('settings/profile', {
        method: 'DELETE',
        body: { password },
    });

export type SecurityData = {
    canManageTwoFactor: boolean;
    canManagePasskeys: boolean;
    passkeys: Passkey[];
    passwordRules: string;
    twoFactorEnabled: boolean;
    requiresConfirmation: boolean;
};

export const securityQuery = queryOptions({
    queryKey: ['user-settings', 'security'],
    queryFn: () => apiFetch<SecurityData>('settings/security'),
});

export const updatePassword = (body: {
    current_password: string;
    password: string;
    password_confirmation: string;
}) =>
    apiFetch<{ message: string }>('settings/password', {
        method: 'PUT',
        body,
    });

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

export const connectionsQuery = queryOptions({
    queryKey: ['user-settings', 'connections'],
    queryFn: () => apiFetch<ConnectionsData>('settings/connections'),
});

export const disconnectConnection = (socialAccountId: string) =>
    apiFetch<{ message: string }>(`settings/connections/${socialAccountId}`, {
        method: 'DELETE',
    });

export type PreferencesMatrix = Record<
    string,
    { in_app: boolean; mail: boolean }
>;

export type NotificationPreferencesData = {
    preferences: PreferencesMatrix;
    alwaysOn: string[];
};

export const notificationPreferencesQuery = queryOptions({
    queryKey: ['user-settings', 'notifications'],
    queryFn: () =>
        apiFetch<NotificationPreferencesData>('settings/notifications'),
});

export const updateNotificationPreferences = (preferences: PreferencesMatrix) =>
    apiFetch<{ message: string }>('settings/notifications', {
        method: 'PUT',
        body: { preferences },
    });

// --- Session-only Fortify + Passkeys endpoints (web routes, not /api/v1) ---

export const confirmedPasswordStatus = () =>
    webFetch<{ confirmed: boolean }>('/user/confirmed-password-status');

export const confirmPassword = (password: string) =>
    webPost('/user/confirm-password', { password });

export const enableTwoFactor = () => webPost('/user/two-factor-authentication');

export const disableTwoFactor = () =>
    webFetch('/user/two-factor-authentication', { method: 'DELETE' });

export const confirmTwoFactor = (code: string) =>
    webPost('/user/confirmed-two-factor-authentication', { code });

export const fetchTwoFactorQrCode = () =>
    webFetch<{ svg: string; url: string }>('/user/two-factor-qr-code');

export const fetchTwoFactorSecretKey = () =>
    webFetch<{ secretKey: string }>('/user/two-factor-secret-key');

export const fetchRecoveryCodes = () =>
    webFetch<string[]>('/user/two-factor-recovery-codes');

export const regenerateRecoveryCodes = () =>
    webPost<string[]>('/user/two-factor-recovery-codes');

export const deletePasskey = (id: number) =>
    webFetch(`/user/passkeys/${id}`, { method: 'DELETE' });
