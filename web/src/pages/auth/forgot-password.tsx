import { useState, type FormEvent } from 'react';

import InputError from '@/components/common/input-error';
import TextLink from '@/components/common/text-link';
import { Button } from '@/components/ui/button';
import { LoaderCircle } from '@/components/ui/icons';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { requestPasswordReset } from '@/features/auth/auth';
import { ApiError } from '@/lib/api';

export default function ForgotPasswordPage() {
    const [status, setStatus] = useState<string>();
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
        void requestPasswordReset(form.get('email') as string)
            .then((result) => setStatus(result.message))
            .catch((error: unknown) => {
                if (error instanceof ApiError) {
                    setErrors({ email: error.fieldError('email') });
                }
            })
            .finally(() => setProcessing(false));
    }

    return (
        <>
            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autoComplete="off"
                            autoFocus
                            placeholder="email@example.com"
                        />

                        <InputError message={errors.email} />
                    </div>

                    <div className="my-6 flex items-center justify-start">
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            )}
                            Email password reset link
                        </Button>
                    </div>
                </form>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Or, return to</span>
                    <TextLink href="/login">log in</TextLink>
                </div>
            </div>
        </>
    );
}
