import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import TwoFactorRecoveryCodes from '@/components/auth/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/auth/two-factor-setup-modal';
import Heading from '@/components/common/heading';
import { Button } from '@/components/ui/button';
import { ShieldCheck } from '@/components/ui/icons';
import {
    disableTwoFactor,
    enableTwoFactor,
    securityQuery,
} from '@/features/user-settings/user-settings';
import { useTwoFactorAuth } from '@/hooks/auth/use-two-factor-auth';
import { ApiError, errorMessage } from '@/lib/api';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

/**
 * Fortify's two-factor endpoints sit behind the `password.confirm` middleware
 * and answer 423 when `auth.password_confirmed_at` has gone stale — send the
 * user through the confirm-password flow and back.
 */
function useConfirmPasswordRedirect() {
    const navigate = useNavigate();

    return (error: unknown): boolean => {
        if (error instanceof ApiError && error.status === 423) {
            void navigate({
                to: '/confirm-password',
                search: { redirect: '/settings/security' },
            });
            return true;
        }

        return false;
    };
}

export default function ManageTwoFactor(props: Props) {
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

    const queryClient = useQueryClient();
    const redirectOn423 = useConfirmPasswordRedirect();
    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    const invalidateSecurity = () =>
        queryClient.invalidateQueries({ queryKey: securityQuery.queryKey });

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    const enable = useMutation({
        mutationFn: enableTwoFactor,
        onSuccess: () => setShowSetupModal(true),
        onError: (error) => {
            if (!redirectOn423(error)) {
                toast.error(errorMessage(error, 'Could not enable 2FA'));
            }
        },
    });

    const disable = useMutation({
        mutationFn: disableTwoFactor,
        onSuccess: invalidateSecurity,
        onError: (error) => {
            if (!redirectOn423(error)) {
                toast.error(errorMessage(error, 'Could not disable 2FA'));
            }
        },
    });

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Two-factor authentication"
                description="Manage your two-factor authentication settings"
            />
            {twoFactorEnabled ? (
                <div className="flex flex-col items-start justify-start space-y-4">
                    <p className="text-sm text-muted-foreground">
                        You will be prompted for a secure, random pin during
                        login, which you can retrieve from the TOTP-supported
                        application on your phone.
                    </p>

                    <div className="relative inline">
                        <Button
                            variant="destructive"
                            disabled={disable.isPending}
                            onClick={() => disable.mutate()}
                        >
                            Disable 2FA
                        </Button>
                    </div>

                    <TwoFactorRecoveryCodes
                        recoveryCodesList={recoveryCodesList}
                        fetchRecoveryCodes={fetchRecoveryCodes}
                        errors={errors}
                    />
                </div>
            ) : (
                <div className="flex flex-col items-start justify-start space-y-4">
                    <p className="text-sm text-muted-foreground">
                        When you enable two-factor authentication, you will be
                        prompted for a secure pin during login. This pin can be
                        retrieved from a TOTP-supported application on your
                        phone.
                    </p>

                    <div>
                        {hasSetupData ? (
                            <Button onClick={() => setShowSetupModal(true)}>
                                <ShieldCheck />
                                Continue setup
                            </Button>
                        ) : (
                            <Button
                                disabled={enable.isPending}
                                onClick={() => enable.mutate()}
                            >
                                Enable 2FA
                            </Button>
                        )}
                    </div>
                </div>
            )}

            <TwoFactorSetupModal
                isOpen={showSetupModal}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </div>
    );
}
