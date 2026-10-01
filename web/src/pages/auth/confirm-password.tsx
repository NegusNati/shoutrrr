import { useNavigate } from '@tanstack/react-router';
import { useEffect, useRef, useState } from 'react';

import PasskeyVerify from '@/components/auth/passkey-verify';
import PasswordInput from '@/components/auth/password-input';
import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ApiError, webPost } from '@/lib/api';
import { fieldString } from '@/lib/forms';

/**
 * Fortify's password-confirmation gate. The web group's password.confirm
 * route redirects here; POST /user/confirm-password sets the
 * auth.password_confirmed_at session flag and we navigate back to the page
 * that asked for confirmation (`redirect` search param).
 *
 * The passkey confirm endpoints live on the same session (web) surface.
 */
const passkeyConfirmRoutes = {
    options: '/passkeys/confirm/options',
    submit: '/passkeys/confirm',
} as const;

export default function ConfirmPasswordPage({ redirect }: { redirect: string }) {
    const navigate = useNavigate();
    const passwordInput = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const [passwordError, setPasswordError] = useState<string>();

    useEffect(() => {
        document.title = 'Confirm password — Shoutrrr';

        return () => {
            document.title = 'Shoutrrr';
        };
    }, []);

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setProcessing(true);
        setPasswordError(undefined);

        try {
            await webPost('/user/confirm-password', {
                password: fieldString(new FormData(e.currentTarget), 'password'),
            });
            void navigate({ to: redirect });
        } catch (error) {
            setPasswordError(
                error instanceof ApiError
                    ? (error.fieldError('password') ?? error.message)
                    : 'Could not confirm your password.',
            );
            passwordInput.current?.focus();
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <PasskeyVerify
                routes={passkeyConfirmRoutes}
                label="Confirm with passkey"
                loadingLabel="Confirming..."
                separator="Or confirm with password"
                redirectTo={redirect}
            />

            <form onSubmit={handleSubmit} className="space-y-6">
                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <PasswordInput
                        ref={passwordInput}
                        id="password"
                        name="password"
                        placeholder="Password"
                        autoComplete="current-password"
                        autoFocus
                    />

                    <InputError message={passwordError} />
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
