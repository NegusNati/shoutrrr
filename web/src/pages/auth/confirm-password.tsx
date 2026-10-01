import { useQuery } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';

import PasskeyVerify from '@/components/auth/passkey-verify';
import PasswordInput from '@/components/auth/password-input';
import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { meQuery } from '@/features/me/me';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError, webPost } from '@/lib/api';
import { fieldString } from '@/lib/forms';
import { appUrl } from '@/lib/href';

export default function ConfirmPassword({ returnTo }: { returnTo?: string }) {
    useDocumentTitle('Confirm password');

    // Mirrors the web `password.confirm` middleware: this page only makes
    // sense signed in — guests go to login and come back afterwards.
    const me = useQuery(meQuery);

    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const [processing, setProcessing] = useState(false);

    const redirectBack = () => {
        const target =
            returnTo && returnTo.startsWith('/')
                ? returnTo
                : appUrl('/dashboard');
        window.location.assign(target);
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
            `/app/login?return_to=${encodeURIComponent(`/app/confirm-password${returnTo ? `?return_to=${encodeURIComponent(returnTo)}` : ''}`)}`,
        );
        return null;
    }

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (processing) {
            return;
        }
        const form = new FormData(event.currentTarget);
        setProcessing(true);
        setErrors({});
        void webPost('/user/confirm-password', {
            password: fieldString(form, 'password'),
        })
            .then(redirectBack)
            .catch((error: unknown) => {
                if (error instanceof ApiError) {
                    setErrors({ password: error.fieldError('password') });
                }
            })
            .finally(() => setProcessing(false));
    }

    return (
        <>
            <PasskeyVerify
                routes={{
                    options: '/passkeys/confirm/options',
                    submit: '/passkeys/confirm',
                }}
                label="Confirm with passkey"
                loadingLabel="Confirming..."
                separator="Or confirm with password"
                onSuccess={redirectBack}
            />

            <form onSubmit={handleSubmit}>
                <div className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            placeholder="Password"
                            autoComplete="current-password"
                            autoFocus
                        />

                        <InputError message={errors.password} />
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
                </div>
            </form>
        </>
    );
}
