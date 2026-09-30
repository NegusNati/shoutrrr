import { usePasskeyVerify } from '@laravel/passkeys/react';

import InputError from '@/components/common/input-error';
import OrSeparator from '@/components/common/or-separator';
import { Button } from '@/components/ui/button';
import { KeyRound } from '@/components/ui/icons';
import { Spinner } from '@/components/ui/spinner';
import { appUrl, toUrl, type Href } from '@/lib/href';

type Props = {
    routes?: {
        options: Href;
        submit: Href;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
    showSeparator?: boolean;
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
    showSeparator = true,
}: Props = {}) {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: toUrl(routes.options),
                submit: toUrl(routes.submit),
            },
        }),
        onSuccess: () => {
            // Passkey sign-in lands on the SPA dashboard; the me query is
            // refetched by the router guard on navigation.
            window.location.href = appUrl('/dashboard');
        },
    });

    if (!isSupported) {
        return null;
    }

    return (
        <>
            <div className="grid gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    onClick={verify}
                    disabled={isLoading}
                >
                    {isLoading ? <Spinner /> : <KeyRound className="h-4 w-4" />}
                    {isLoading
                        ? (loadingLabel ?? 'Authenticating...')
                        : (label ?? 'Sign in with a passkey')}
                </Button>
                {error && (
                    <InputError message={error} className="text-center" />
                )}
            </div>

            {showSeparator && (
                <OrSeparator>
                    {separator ?? 'Or continue with email'}
                </OrSeparator>
            )}
        </>
    );
}
