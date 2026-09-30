import { createFileRoute } from '@tanstack/react-router';

import AuthLayout from '@/layouts/auth-layout';
import VerifyEmailPage from '@/pages/auth/verify-email';

export const Route = createFileRoute('/verify-email')({
    component: VerifyEmailRoute,
});

function VerifyEmailRoute() {
    return (
        <AuthLayout
            title="Email verification"
            description="Please verify your email address by clicking on the link we just emailed to you."
        >
            <VerifyEmailPage />
        </AuthLayout>
    );
}
