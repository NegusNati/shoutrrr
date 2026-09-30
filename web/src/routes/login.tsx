import { createFileRoute } from '@tanstack/react-router';

import AuthLayout from '@/layouts/auth-layout';
import LoginPage from '@/pages/auth/login';

type LoginSearch = {
    status?: string;
    invitation?: string;
};

export const Route = createFileRoute('/login')({
    validateSearch: (search: Record<string, unknown>): LoginSearch => ({
        status: typeof search.status === 'string' ? search.status : undefined,
        invitation:
            typeof search.invitation === 'string'
                ? search.invitation
                : undefined,
    }),
    component: LoginRoute,
});

function LoginRoute() {
    const search = Route.useSearch();

    return (
        <AuthLayout
            title="Log in to your account"
            description="Enter your email and password below to log in"
            brandText="Shoutrrr"
        >
            <LoginPage status={search.status} invitation={search.invitation} />
        </AuthLayout>
    );
}
