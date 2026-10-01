import { useQuery } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';

import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/auth/passkey-verify';
import PasswordInput from '@/components/auth/password-input';
import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { meQuery } from '@/features/me/me';
import { confirmPassword } from '@/features/user-settings/user-settings';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError, errorMessage } from '@/lib/api';
import { fieldString } from '@/lib/forms';

export default function ConfirmPasswordPage({
    redirect = '/dashboard',
}: {
    redirect?: string;
}) {
    useDocumentTitle('Confirm password');
    const navigate = useNavigate();

    // Mirrors the web `password.confirm` middleware: this page only makes
    // sense signed in — guests go to login and come back afterwards.
    const me = useQuery(meQuery);

    const [passwordError, setPasswordError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const goBack = () => {
        void navigate({ to: redirect });
    };

    if (me.isPending) {
        return (
            <div className="flex min-h-[200px] items-center justify-center">
                <Spinner className="size-5" />
            </div>
        );
    }

    if (me.isError) {
        window.location.assign(
            `/app/login?return_to=${encodeURIComponent(`/app/confirm-password?redirect=${encodeURIComponent(redirect)}`)}`,
        );
        return null;
    }

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (processing) {
            return;
        }
        const password = fieldString(
            new FormData(event.currentTarget),
            'password',
        );
        setProcessing(true);
        setPasswordError(null);
        confirmPassword(password)
            .then(goBack)
            .catch((error: unknown) => {
                if (error instanceof ApiError && error.status === 422) {
                    setPasswordError(
                        error.fieldError('password') ?? error.message,
                    );
                } else {
                    toast.error(
                        errorMessage(error, 'Could not confirm the password.'),
                    );
                }
            })
            .finally(() => setProcessing(false));
    }

    return (
        <>
            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Confirm with passkey"
                loadingLabel="Confirming..."
                separator="Or confirm with password"
                onSuccess={goBack}
            />

            <form onSubmit={handleSubmit} className="space-y-6">
                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        placeholder="Password"
                        autoComplete="current-password"
                        autoFocus
                    />

                    <InputError message={passwordError ?? undefined} />
                </div>

                <div className="flex items-center">
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={processing}
                        data-test="confirm-password-button"
                    >
                        {processing && <Spinner />}
                        Confirm password
                    </Button>
                </div>
            </form>
        </>
    );
}
