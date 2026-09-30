import { useQuery } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useState, type FormEvent } from 'react';

import PasskeyVerify from '@/components/auth/passkey-verify';
import PasswordInput from '@/components/auth/password-input';
import InputError from '@/components/common/input-error';
import OrSeparator from '@/components/common/or-separator';
import TextLink from '@/components/common/text-link';
import SocialLoginList from '@/components/socialite/social-login-list';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { authOptionsQuery, login } from '@/features/auth/auth';
import { ApiError } from '@/lib/api';
import { fieldString } from '@/lib/forms';

export default function LoginPage({
    status,
    invitation,
}: {
    status?: string;
    invitation?: string;
}) {
    const navigate = useNavigate();
    const { data: options } = useQuery(authOptionsQuery(invitation));
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const [processing, setProcessing] = useState(false);

    const providers = options?.providers ?? [];
    const canResetPassword = options?.canResetPassword ?? false;
    const canRegister = options?.canRegister ?? true;

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (processing) {
            return;
        }
        const form = new FormData(event.currentTarget);
        setProcessing(true);
        setErrors({});
        void login({
            email: fieldString(form, 'email'),
            password: fieldString(form, 'password'),
            remember: form.get('remember') === 'on',
        })
            .then((result) => {
                if (result.two_factor) {
                    void navigate({ to: '/two-factor-challenge' });
                    return;
                }
                void navigate({ to: '/dashboard' });
            })
            .catch((error: unknown) => {
                if (error instanceof ApiError) {
                    setErrors({
                        email: error.fieldError('email'),
                        password: error.fieldError('password'),
                    });
                }
            })
            .finally(() => setProcessing(false));
    }

    return (
        <>
            {providers.length > 0 ? (
                <div>
                    <div className="flex flex-col gap-3">
                        <SocialLoginList
                            providers={providers}
                            invitation={invitation}
                        />
                        <PasskeyVerify showSeparator={false} />
                    </div>

                    <OrSeparator />
                </div>
            ) : (
                <PasskeyVerify />
            )}

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="email"
                            placeholder="email@example.com"
                            defaultValue={options?.defaultLogin?.email}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="password">Password</Label>
                            {canResetPassword && (
                                <TextLink
                                    href="/forgot-password"
                                    className="ml-auto text-sm"
                                    tabIndex={5}
                                >
                                    Forgot your password?
                                </TextLink>
                            )}
                        </div>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            placeholder="Password"
                            defaultValue={options?.defaultLogin?.password}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" tabIndex={3} />
                        <Label htmlFor="remember">Remember me</Label>
                    </div>

                    <Button
                        type="submit"
                        className="mt-4 w-full"
                        tabIndex={4}
                        disabled={processing}
                        data-test="login-button"
                    >
                        {processing && <Spinner />}
                        Log in
                    </Button>
                </div>

                {canRegister ? (
                    <div className="text-center text-sm text-muted-foreground">
                        Don't have an account?{' '}
                        <TextLink
                            href={
                                invitation
                                    ? `/register?invitation=${invitation}`
                                    : '/register'
                            }
                            tabIndex={5}
                        >
                            Sign up
                        </TextLink>
                    </div>
                ) : (
                    <div className="text-center text-sm text-muted-foreground">
                        {options?.registrationDisabledMessage}
                    </div>
                )}
            </form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}
