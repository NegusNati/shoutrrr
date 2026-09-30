import { createFileRoute } from '@tanstack/react-router';

import AuthLayout from '@/layouts/auth-layout';
import ForgotPasswordPage from '@/pages/auth/forgot-password';

export const Route = createFileRoute('/forgot-password')({
    component: ForgotPasswordRoute,
});

function ForgotPasswordRoute() {
    return (
        <AuthLayout
            title="Forgot password"
            description="Enter your email to receive a password reset link"
        >
            <ForgotPasswordPage />
        </AuthLayout>
    );
}
