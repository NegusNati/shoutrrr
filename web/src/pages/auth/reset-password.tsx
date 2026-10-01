import { useQuery } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useState, type FormEvent } from 'react';

import PasswordInput from '@/components/auth/password-input';
import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { authOptionsQuery, resetPassword } from '@/features/auth/auth';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError } from '@/lib/api';
import { fieldString } from '@/lib/forms';

export default function ResetPasswordPage({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    useDocumentTitle('Reset password');
    const navigate = useNavigate();
    const { data: options } = useQuery(authOptionsQuery());
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const [processing, setProcessing] = useState(false);

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (processing) {
            return;
        }
        const form = new FormData(event.currentTarget);
        setProcessing(true);
        setErrors({});
        void resetPassword({
            token,
            email,
            password: fieldString(form, 'password'),
            password_confirmation: fieldString(form, 'password_confirmation'),
        })
            .then((result) => {
                void navigate({
                    to: '/login',
                    search: { status: result.message },
                });
            })
            .catch((error: unknown) => {
                if (error instanceof ApiError) {
                    setErrors({
                        email: error.fieldError('email'),
                        password: error.fieldError('password'),
                        password_confirmation: error.fieldError(
                            'password_confirmation',
                        ),
                    });
                }
            })
            .finally(() => setProcessing(false));
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autoComplete="email"
                        value={email}
                        className="mt-1 block w-full"
                        readOnly
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autoComplete="new-password"
                        className="mt-1 block w-full"
                        autoFocus
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
                        name="password_confirmation"
                        autoComplete="new-password"
                        className="mt-1 block w-full"
                        placeholder="Confirm password"
                        passwordrules={options?.passwordRules}
                    />
                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <Button
                    type="submit"
                    className="mt-4 w-full"
                    disabled={processing}
                    data-test="reset-password-button"
                >
                    {processing && <Spinner />}
                    Reset password
                </Button>
            </div>
        </form>
    );
}
