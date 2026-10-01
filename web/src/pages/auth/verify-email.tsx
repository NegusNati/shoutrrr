import { useState } from 'react';

import TextLink from '@/components/common/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { resendVerificationEmail } from '@/features/auth/auth';
import { logout } from '@/features/auth/logout';
import { useDocumentTitle } from '@/hooks/use-document-title';

export default function VerifyEmailPage() {
    useDocumentTitle('Verify email');
    const [status, setStatus] = useState<string>();
    const [processing, setProcessing] = useState(false);

    function resend() {
        setProcessing(true);
        void resendVerificationEmail()
            .then((result) => setStatus(result.message))
            .finally(() => setProcessing(false));
    }

    return (
        <div className="space-y-6 text-center">
            {status && (
                <div className="text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <Button
                type="button"
                onClick={resend}
                disabled={processing}
                variant="secondary"
            >
                {processing && <Spinner />}
                Resend verification email
            </Button>

            <button
                type="button"
                onClick={() => void logout()}
                className="mx-auto block text-sm text-muted-foreground underline hover:text-foreground"
            >
                Log out
            </button>

            <TextLink href="/login" className="mx-auto block text-sm">
                Back to log in
            </TextLink>
        </div>
    );
}
