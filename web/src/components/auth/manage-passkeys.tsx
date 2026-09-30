import { useQueryClient } from '@tanstack/react-query';

import PasskeyItem from '@/components/auth/passkey-item';
import PasskeyRegistration from '@/components/auth/passkey-register';
import Heading from '@/components/common/heading';
import { KeyRound } from '@/components/ui/icons';
import { fortifyRoutes, settingsKeys } from '@/features/settings/settings';
import { webFetch } from '@/lib/api';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

const EmptyState = () => {
    return (
        <div className="p-8 text-center">
            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
                <KeyRound className="h-7 w-7 text-muted-foreground" />
            </div>
            <p className="font-medium">No passkeys yet</p>
            <p className="mt-1 text-sm text-muted-foreground">
                Add a passkey to sign in without a password
            </p>
        </div>
    );
};

export default function ManagePasskeys(props: Props) {
    const passkeys = props.passkeys ?? [];
    const queryClient = useQueryClient();

    const refetchSecurity = () =>
        queryClient.invalidateQueries({ queryKey: settingsKeys.security });

    const handleDelete = async (id: number, onError: () => void) => {
        try {
            await webFetch(fortifyRoutes.passkeyDestroy(id), {
                method: 'DELETE',
            });
            await refetchSecurity();
        } catch {
            onError();
        }
    };

    const handleRegisterSuccess = () => {
        void refetchSecurity();
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Passkeys"
                description="Manage your passkeys for passwordless sign-in"
            />

            <div className="overflow-hidden rounded-lg border border-border">
                {passkeys.length > 0 ? (
                    passkeys.map((passkey) => (
                        <PasskeyItem
                            key={passkey.id}
                            passkey={passkey}
                            onDelete={(id, onError) =>
                                void handleDelete(id, onError)
                            }
                        />
                    ))
                ) : (
                    <EmptyState />
                )}
            </div>

            <PasskeyRegistration onSuccess={handleRegisterSuccess} />
        </div>
    );
}
