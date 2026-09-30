import { createFileRoute } from '@tanstack/react-router';

import AuthLayout from '@/layouts/auth-layout';
import ResetPasswordPage from '@/pages/auth/reset-password';

type ResetPasswordSearch = {
    token: string;
    email: string;
};

export const Route = createFileRoute('/reset-password')({
    validateSearch: (search: Record<string, unknown>): ResetPasswordSearch => ({
        token: typeof search.token === 'string' ? search.token : '',
        email: typeof search.email === 'string' ? search.email : '',
    }),
    component: ResetPasswordRoute,
});

function ResetPasswordRoute() {
    const { token, email } = Route.useSearch();

    return (
        <AuthLayout
            title="Reset password"
            description="Please enter your new password below"
        >
            <ResetPasswordPage token={token} email={email} />
        </AuthLayout>
    );
}
