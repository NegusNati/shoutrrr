import { useMutation, useQuery } from '@tanstack/react-query';
import { useRef, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

import ManagePasskeys from '@/components/auth/manage-passkeys';
import ManageTwoFactor from '@/components/auth/manage-two-factor';
import PasswordInput from '@/components/auth/password-input';
import Heading from '@/components/common/heading';
import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    securityQuery,
    updatePassword,
} from '@/features/user-settings/user-settings';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError, errorMessage } from '@/lib/api';
import { fieldString } from '@/lib/forms';

export default function SecurityPage() {
    useDocumentTitle('Security settings');

    const { data } = useQuery(securityQuery);
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const formRef = useRef<HTMLFormElement>(null);
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );

    const save = useMutation({
        mutationFn: updatePassword,
        onSuccess: () => {
            setErrors({});
            formRef.current?.reset();
            toast.success('Password updated.');
        },
        onError: (error) => {
            // resetOnError: clear the three fields like the Inertia form did.
            formRef.current
                ?.querySelectorAll('input[type="password"]')
                .forEach((input) => {
                    (input as HTMLInputElement).value = '';
                });

            if (error instanceof ApiError && error.status === 422) {
                const next = {
                    current_password: error.fieldError('current_password'),
                    password: error.fieldError('password'),
                    password_confirmation: error.fieldError(
                        'password_confirmation',
                    ),
                };
                setErrors(next);

                if (next.password) {
                    passwordInput.current?.focus();
                }
                if (next.current_password) {
                    currentPasswordInput.current?.focus();
                }
            } else {
                toast.error(
                    errorMessage(error, 'Could not update your password.'),
                );
            }
        },
    });

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        save.mutate({
            current_password: fieldString(form, 'current_password'),
            password: fieldString(form, 'password'),
            password_confirmation: fieldString(form, 'password_confirmation'),
        });
    }

    return (
        <>
            <h1 className="sr-only">Security settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Update password"
                    description="Ensure your account is using a long, random password to stay secure"
                />

                <form
                    ref={formRef}
                    onSubmit={handleSubmit}
                    className="space-y-6"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="current_password">
                            Current password
                        </Label>

                        <PasswordInput
                            id="current_password"
                            ref={currentPasswordInput}
                            name="current_password"
                            className="mt-1 block w-full"
                            autoComplete="current-password"
                            placeholder="Current password"
                        />

                        <InputError message={errors.current_password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">New password</Label>

                        <PasswordInput
                            id="password"
                            ref={passwordInput}
                            name="password"
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            placeholder="New password"
                            passwordrules={data?.passwordRules}
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
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            placeholder="Confirm password"
                            passwordrules={data?.passwordRules}
                        />

                        <InputError message={errors.password_confirmation} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={save.isPending}
                            data-test="update-password-button"
                        >
                            Save
                        </Button>
                    </div>
                </form>
            </div>

            <ManageTwoFactor
                canManageTwoFactor={data?.canManageTwoFactor}
                requiresConfirmation={data?.requiresConfirmation}
                twoFactorEnabled={data?.twoFactorEnabled}
            />

            <ManagePasskeys
                canManagePasskeys={data?.canManagePasskeys}
                passkeys={data?.passkeys}
            />
        </>
    );
}
