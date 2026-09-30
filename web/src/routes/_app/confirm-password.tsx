import { createFileRoute } from '@tanstack/react-router';

import AuthLayout from '@/layouts/auth-layout';
import ConfirmPasswordPage from '@/pages/auth/confirm-password';

type ConfirmSearch = {
    /** SPA-relative path to return to after confirming (e.g. '/settings/security'). */
    redirect?: string;
};

// Inside the authed `_app` layout — Fortify's /user/confirm-password is
// auth-protected too, so the me guard doubles as the session check.
export const Route = createFileRoute('/_app/confirm-password')({
    validateSearch: (search: Record<string, unknown>): ConfirmSearch => ({
        // Only same-app absolute paths — never a scheme/host (open redirect).
        redirect:
            typeof search.redirect === 'string' &&
            /^\/(?!\/)/.test(search.redirect)
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
            brandText="Shoutrrr"
        >
            <ConfirmPasswordPage redirect={redirect ?? '/dashboard'} />
        </AuthLayout>
    );
}
