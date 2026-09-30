import { createFileRoute } from '@tanstack/react-router';

import AuthLayout from '@/layouts/auth-layout';
import TwoFactorChallengePage from '@/pages/auth/two-factor-challenge';

export const Route = createFileRoute('/two-factor-challenge')({
    component: TwoFactorChallengeRoute,
});

function TwoFactorChallengeRoute() {
    return (
        <AuthLayout
            title="Two-factor authentication"
            description="Confirm access to your account"
        >
            <TwoFactorChallengePage />
        </AuthLayout>
    );
}
