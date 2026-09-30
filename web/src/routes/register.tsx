import { createFileRoute } from '@tanstack/react-router';

import AuthLayout from '@/layouts/auth-layout';
import RegisterPage from '@/pages/auth/register';

type RegisterSearch = {
    invitation?: string;
};

export const Route = createFileRoute('/register')({
    validateSearch: (search: Record<string, unknown>): RegisterSearch => ({
        invitation:
            typeof search.invitation === 'string'
                ? search.invitation
                : undefined,
    }),
    component: RegisterRoute,
});

function RegisterRoute() {
    const { invitation } = Route.useSearch();

    return (
        <AuthLayout
            title="Create an account"
            description="Enter your details below to create your account"
            brandText="Shoutrrr"
        >
            <RegisterPage invitation={invitation} />
        </AuthLayout>
    );
}
