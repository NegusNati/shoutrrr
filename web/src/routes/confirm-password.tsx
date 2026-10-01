import { createFileRoute } from '@tanstack/react-router';

import AuthLayout from '@/layouts/auth-layout';
import ConfirmPasswordPage from '@/pages/auth/confirm-password';

type ConfirmSearch = {
    return_to?: string;
};

export const Route = createFileRoute('/confirm-password')({
    validateSearch: (search: Record<string, unknown>): ConfirmSearch => ({
        return_to:
            typeof search.return_to === 'string' ? search.return_to : undefined,
    }),
    component: ConfirmRoute,
});

function ConfirmRoute() {
    const { return_to } = Route.useSearch();

    return (
        <AuthLayout
            title="Confirm password"
            description="This is a secure area of the application. Please confirm your password before continuing."
            brandText="Shoutrrr"
        >
            <ConfirmPasswordPage returnTo={return_to} />
        </AuthLayout>
    );
}
