import { useMutation } from '@tanstack/react-query';
import { useRef, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

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
import { deleteAccount } from '@/features/user-settings/user-settings';
import { ApiError } from '@/lib/api';
import { fieldString } from '@/lib/forms';
import { queryClient } from '@/lib/query-client';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [passwordError, setPasswordError] = useState<string | null>(null);

    const destroy = useMutation({
        mutationFn: deleteAccount,
        onSuccess: async () => {
            await queryClient.invalidateQueries();
            window.location.href = '/';
        },
        onError: (error) => {
            passwordInput.current?.focus();
            if (error instanceof ApiError && error.status === 422) {
                setPasswordError(error.fieldError('password') ?? error.message);
            } else {
                setOpen(false);
                toast.error('Could not delete your account.');
            }
        },
    });

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const password = fieldString(
            new FormData(event.currentTarget),
            'password',
        );
        setPasswordError(null);
        destroy.mutate(password);
    }

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

                <Dialog open={open} onOpenChange={setOpen}>
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

                                <InputError
                                    message={passwordError ?? undefined}
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose
                                    render={
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                setPasswordError(null)
                                            }
                                        />
                                    }
                                >
                                    Cancel
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    disabled={destroy.isPending}
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
