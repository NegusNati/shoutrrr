import { useRef, useState } from 'react';

import PasswordInput from '@/components/auth/password-input';
import Heading from '@/components/common/heading';
import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { endpoints } from '@/lib/api/endpoints';
import { ApiError, apiFetch } from '@/lib/api';
import { appUrl } from '@/lib/href';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const form = e.currentTarget;
        const password = new FormData(form).get('password');

        setProcessing(true);
        setError(null);

        try {
            await apiFetch(endpoints.settingsProfile, {
                method: 'DELETE',
                body: { password: typeof password === 'string' ? password : '' },
            });
            window.location.href = appUrl('/login');
        } catch (err) {
            setError(
                err instanceof ApiError
                    ? (err.errors.password?.[0] ?? err.message)
                    : 'Failed to delete the account',
            );
            passwordInput.current?.focus();
        } finally {
            setProcessing(false);
        }
    };

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Delete account"
                description="Delete your account and all of its resources"
            />
            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                    <p className="font-medium">Warning</p>
                    <p className="text-sm">
                        Please proceed with caution, this cannot be undone.
                    </p>
                </div>

                <Dialog>
                    <DialogTrigger
                        render={
                            <Button
                                variant="destructive"
                                data-test="delete-user-button"
                            />
                        }
                    >
                        Delete account
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Are you sure you want to delete your account?
                        </DialogTitle>
                        <DialogDescription>
                            Once your account is deleted, all of its resources
                            and data will also be permanently deleted. Please
                            enter your password to confirm you would like to
                            permanently delete your account.
                        </DialogDescription>

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="password" className="sr-only">
                                    Password
                                </Label>

                                <PasswordInput
                                    id="password"
                                    name="password"
                                    ref={passwordInput}
                                    placeholder="Password"
                                    autoComplete="current-password"
                                />

                                <InputError message={error ?? undefined} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose
                                    render={<Button variant="secondary" />}
                                >
                                    Cancel
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                    render={
                                        <button
                                            type="submit"
                                            data-test="confirm-delete-user-button"
                                        />
                                    }
                                >
                                    Delete account
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
