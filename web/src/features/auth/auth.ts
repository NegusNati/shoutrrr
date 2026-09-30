import { queryOptions } from '@tanstack/react-query';

import { api, webPost } from '@/lib/api';
import { queryClient } from '@/lib/query-client';
import type { SocialProviderOption } from '@/types/auth';

export type AuthOptions = {
    canResetPassword: boolean;
    canRegister: boolean;
    registrationDisabledMessage: string | null;
    providers: SocialProviderOption[];
    invitation: string | null;
    invitationEmail: string | null;
    passwordRules: string;
    defaultLogin?: { email: string; password: string };
};

/** Public auth-page metadata — the /api/v1 analogue of Inertia page props. */
export const authOptionsQuery = (invitation?: string) =>
    queryOptions({
        queryKey: ['auth-options', invitation ?? null],
        queryFn: () =>
            api.get<AuthOptions>(
                `auth/options${invitation ? `?invitation=${invitation}` : ''}`,
            ),
        staleTime: 60_000,
    });

export type LoginBody = {
    email: string;
    password: string;
    remember?: boolean;
};

export type LoginResult = { two_factor: boolean };

export async function login(body: LoginBody): Promise<LoginResult> {
    const result = await webPost<LoginResult>('/login', body);
    if (result.two_factor) {
        return result;
    }
    await queryClient.invalidateQueries();
    return result;
}

export type RegisterBody = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    invitation?: string;
};

export async function register(body: RegisterBody): Promise<void> {
    await webPost('/register', body);
    await queryClient.invalidateQueries();
}

export async function requestPasswordReset(email: string) {
    return webPost<{ message: string }>('/forgot-password', { email });
}

export type ResetPasswordBody = {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
};

export async function resetPassword(body: ResetPasswordBody) {
    return webPost<{ message: string }>('/reset-password', body);
}

export type TwoFactorChallengeBody =
    | { code: string }
    | { recovery_code: string };

export async function submitTwoFactorChallenge(
    body: TwoFactorChallengeBody,
): Promise<void> {
    await webPost('/two-factor-challenge', body);
    await queryClient.invalidateQueries();
}

export async function resendVerificationEmail() {
    return webPost<{ message: string }>('/email/verification-notification');
}
