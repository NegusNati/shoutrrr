import { useQuery } from '@tanstack/react-query';
import { useRef, useState } from 'react';

import ManagePasskeys from '@/components/auth/manage-passkeys';
import ManageTwoFactor from '@/components/auth/manage-two-factor';
import PasswordInput from '@/components/auth/password-input';
import Heading from '@/components/common/heading';
import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { securityQuery } from '@/features/settings/settings';
import { ApiError, apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { fieldString } from '@/lib/forms';

export default function Security() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const formRef = useRef<HTMLFormElement>(null);
    const { data } = useQuery(securityQuery);

    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const data = new FormData(e.currentTarget);

        setProcessing(true);
        setErrors({});

        try {
            await apiFetch(endpoints.settingsPassword, {
                method: 'PUT',
                body: {
                    current_password: fieldString(data, 'current_password'),
                    password: fieldString(data, 'password'),
                    password_confirmation: fieldString(
                        data,
                        'password_confirmation',
                    ),
                },
            });
            formRef.current?.reset();
        } catch (err) {
            const fieldErrors =
                err instanceof ApiError
                    ? Object.fromEntries(
                          Object.entries(err.errors).map(([k, v]) => [k, v[0]]),
                      )
                    : { _: 'Failed to update password' };
            setErrors(fieldErrors);
            formRef.current?.reset();
            if (fieldErrors.password) {
                passwordInput.current?.focus();
            }
            if (fieldErrors.current_password) {
                currentPasswordInput.current?.focus();
            }
        } finally {
            setProcessing(false);
        }
    };

    if (!data) {
        return null;
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
                        <Label htmlFor="current_password">Current password</Label>

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
                            passwordrules={data.passwordRules}
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
                            passwordrules={data.passwordRules}
                        />

                        <InputError message={errors.password_confirmation} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="update-password-button"
                        >
                            Save
                        </Button>
                    </div>
                </form>
            </div>

            <ManageTwoFactor
                canManageTwoFactor={data.canManageTwoFactor}
                requiresConfirmation={data.requiresConfirmation}
                twoFactorEnabled={data.twoFactorEnabled}
            />

            <ManagePasskeys
                canManagePasskeys={data.canManagePasskeys}
                passkeys={data.passkeys}
            />
        </>
    );
}
