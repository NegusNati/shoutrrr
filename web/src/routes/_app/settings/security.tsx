import { createFileRoute, redirect } from '@tanstack/react-router';

import { securityQuery } from '@/features/settings/settings';
import { ApiError } from '@/lib/api';
import Security from '@/pages/settings/security';

export const Route = createFileRoute('/_app/settings/security')({
    loader: async ({ context: { queryClient } }) => {
        try {
            await queryClient.ensureQueryData(securityQuery);
        } catch (error) {
            // RequirePassword on the API answers 423 when the session hasn't
            // confirmed recently — send the user through the confirm page and
            // straight back here (mirrors the web password.confirm redirect).
            if (error instanceof ApiError && error.status === 423) {
                throw redirect({
                    to: '/confirm-password',
                    search: { redirect: '/settings/security' },
                });
            }
            throw error;
        }
    },
    component: Security,
});
