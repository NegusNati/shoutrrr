import { useEffect, useRef, useState } from 'react';

import TwoFactorRecoveryCodes from '@/components/auth/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/auth/two-factor-setup-modal';
import Heading from '@/components/common/heading';
import { Button } from '@/components/ui/button';
import { ShieldCheck } from '@/components/ui/icons';
import { fortifyRoutes, settingsKeys } from '@/features/settings/settings';
import { useTwoFactorAuth } from '@/hooks/auth/use-two-factor-auth';
import { webFetch, webPost } from '@/lib/api';
import { useQueryClient } from '@tanstack/react-query';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function ManageTwoFactor(props: Props) {
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

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
    const [processing, setProcessing] = useState(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);
    const queryClient = useQueryClient();

    const refetchSecurity = () =>
        queryClient.invalidateQueries({ queryKey: settingsKeys.security });

    const handleEnable = async () => {
        setProcessing(true);
        try {
            await webPost(fortifyRoutes.twoFactorEnable);
            setShowSetupModal(true);
            await refetchSecurity();
        } finally {
            setProcessing(false);
        }
    };

    const handleDisable = async () => {
        setProcessing(true);
        try {
            await webFetch(fortifyRoutes.twoFactorDisable, { method: 'DELETE' });
            await refetchSecurity();
        } finally {
            setProcessing(false);
        }
    };

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

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
                            disabled={processing}
                            onClick={handleDisable}
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
                                disabled={processing}
                                onClick={handleEnable}
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
