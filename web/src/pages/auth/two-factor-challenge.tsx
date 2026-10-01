import { useNavigate } from '@tanstack/react-router';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState, type FormEvent } from 'react';

import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { submitTwoFactorChallenge } from '@/features/auth/auth';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError } from '@/lib/api';
import { fieldString } from '@/lib/forms';

const OTP_MAX_LENGTH = 6;

export default function TwoFactorChallengePage() {
    useDocumentTitle('Two-factor authentication');
    const navigate = useNavigate();
    const [showRecoveryInput, setShowRecoveryInput] = useState(false);
    const [code, setCode] = useState('');
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const [processing, setProcessing] = useState(false);

    function toggleRecoveryMode() {
        setShowRecoveryInput((current) => !current);
        setErrors({});
        setCode('');
    }

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (processing) {
            return;
        }
        const form = new FormData(event.currentTarget);
        const body = showRecoveryInput
            ? { recovery_code: fieldString(form, 'recovery_code') }
            : { code };
        setProcessing(true);
        setErrors({});
        void submitTwoFactorChallenge(body)
            .then(() => {
                void navigate({ to: '/dashboard' });
            })
            .catch((error: unknown) => {
                if (error instanceof ApiError) {
                    setErrors({
                        code: error.fieldError('code'),
                        recovery_code: error.fieldError('recovery_code'),
                    });
                    setCode('');
                }
            })
            .finally(() => setProcessing(false));
    }

    return (
        <div className="space-y-6">
            <form onSubmit={handleSubmit} className="space-y-4">
                {showRecoveryInput ? (
                    <>
                        <Input
                            name="recovery_code"
                            type="text"
                            placeholder="Enter recovery code"
                            autoFocus={showRecoveryInput}
                            required
                        />
                        <InputError message={errors.recovery_code} />
                    </>
                ) : (
                    <div className="flex flex-col items-center justify-center space-y-3 text-center">
                        <div className="flex w-full items-center justify-center">
                            <InputOTP
                                name="code"
                                maxLength={OTP_MAX_LENGTH}
                                value={code}
                                onChange={(value) => setCode(value)}
                                disabled={processing}
                                pattern={REGEXP_ONLY_DIGITS}
                                autoFocus
                            >
                                <InputOTPGroup>
                                    {Array.from(
                                        { length: OTP_MAX_LENGTH },
                                        (_, index) => (
                                            <InputOTPSlot
                                                key={index}
                                                index={index}
                                            />
                                        ),
                                    )}
                                </InputOTPGroup>
                            </InputOTP>
                        </div>
                        <InputError message={errors.code} />
                    </div>
                )}

                <Button type="submit" className="w-full" disabled={processing}>
                    Continue
                </Button>

                <div className="text-center text-sm text-muted-foreground">
                    <span>or you can </span>
                    <button
                        type="button"
                        className="cursor-pointer text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        onClick={toggleRecoveryMode}
                    >
                        {showRecoveryInput
                            ? 'login using an authentication code'
                            : 'login using a recovery code'}
                    </button>
                </div>
            </form>
        </div>
    );
}
