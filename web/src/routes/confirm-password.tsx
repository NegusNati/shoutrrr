import { createFileRoute } from '@tanstack/react-router';

import AuthLayout from '@/layouts/auth-layout';
import ConfirmPasswordPage from '@/pages/auth/confirm-password';

type ConfirmPasswordSearch = {
    redirect?: string;
};

export const Route = createFileRoute('/confirm-password')({
    validateSearch: (
        search: Record<string, unknown>,
    ): ConfirmPasswordSearch => ({
        redirect:
            typeof search.redirect === 'string' &&
            search.redirect.startsWith('/')
                ? search.redirect
                : undefined,
    }),
    component: ConfirmPasswordRoute,
});

function ConfirmPasswordRoute() {
    const { redirect } = Route.useSearch();

    return (
        <AuthLayout
            title="Confirm password"
            description="This is a secure area of the application. Please confirm your password before continuing."
        >
            <ConfirmPasswordPage redirect={redirect} />
        </AuthLayout>
    );
}
