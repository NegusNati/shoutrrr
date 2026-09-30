import { useQuery } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useState, type FormEvent } from 'react';

import PasswordInput from '@/components/auth/password-input';
import InputError from '@/components/common/input-error';
import OrSeparator from '@/components/common/or-separator';
import TextLink from '@/components/common/text-link';
import SocialLoginList from '@/components/socialite/social-login-list';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { authOptionsQuery, register } from '@/features/auth/auth';
import { ApiError } from '@/lib/api';
import { appUrl } from '@/lib/href';

export default function RegisterPage({ invitation }: { invitation?: string }) {
    const navigate = useNavigate();
    const { data: options } = useQuery(authOptionsQuery(invitation));
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const [processing, setProcessing] = useState(false);

    const providers = options?.providers ?? [];
    const invitationEmail = options?.invitationEmail;

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (processing) {
            return;
        }
        const form = new FormData(event.currentTarget);
        setProcessing(true);
        setErrors({});
        void register({
            name: form.get('name') as string,
            email: form.get('email') as string,
            password: form.get('password') as string,
            password_confirmation: form.get('password_confirmation') as string,
            ...(invitation ? { invitation } : {}),
        })
            .then(() => {
                void navigate({ to: '/dashboard' });
            })
            .catch((error: unknown) => {
                if (error instanceof ApiError) {
                    setErrors({
                        name: error.fieldError('name'),
                        email: error.fieldError('email'),
                        password: error.fieldError('password'),
                        password_confirmation: error.fieldError(
                            'password_confirmation',
                        ),
                        invitation: error.fieldError('invitation'),
                    });
                }
            })
            .finally(() => setProcessing(false));
    }

    return (
        <>
            {providers.length > 0 && (
                <div>
                    <SocialLoginList
                        providers={providers}
                        invitation={invitation}
                    />
                    <OrSeparator />
                </div>
            )}

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="name"
                            name="name"
                            placeholder="Full name"
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            tabIndex={2}
                            autoComplete="email"
                            name="email"
                            placeholder="email@example.com"
                            defaultValue={invitationEmail ?? undefined}
                            readOnly={Boolean(invitationEmail)}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <PasswordInput
                            id="password"
                            required
                            tabIndex={3}
                            autoComplete="new-password"
                            name="password"
                            placeholder="Password"
                            passwordrules={options?.passwordRules}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Confirm password
                        </Label>
                        <PasswordInput
                            id="password_confirmation"
                            required
                            tabIndex={4}
                            autoComplete="new-password"
                            name="password_confirmation"
                            placeholder="Confirm password"
                            passwordrules={options?.passwordRules}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    {errors.invitation && (
                        <InputError message={errors.invitation} />
                    )}

                    <Button
                        type="submit"
                        className="mt-2 w-full"
                        tabIndex={5}
                        data-test="register-user-button"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Create account
                    </Button>
                </div>

                <div className="text-center text-sm text-muted-foreground">
                    Already have an account?{' '}
                    <TextLink
                        href={appUrl(
                            invitation
                                ? `/login?invitation=${invitation}`
                                : '/login',
                        )}
                        tabIndex={6}
                    >
                        Log in
                    </TextLink>
                </div>
            </form>
        </>
    );
}
