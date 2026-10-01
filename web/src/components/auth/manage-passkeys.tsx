import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import PasskeyItem from '@/components/auth/passkey-item';
import PasskeyRegistration from '@/components/auth/passkey-register';
import Heading from '@/components/common/heading';
import { KeyRound } from '@/components/ui/icons';
import {
    deletePasskey,
    securityQuery,
    type SecurityData,
} from '@/features/user-settings/user-settings';
import { errorMessage } from '@/lib/api';
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

    // Passkey ids are numeric, so the optimistic filter maps over the security
    // payload directly rather than through removeById (which keys on strings).
    const remove = useMutation({
        mutationFn: deletePasskey,
        onMutate: async (id) => {
            await queryClient.cancelQueries({
                queryKey: securityQuery.queryKey,
            });
            const previous = queryClient.getQueryData<SecurityData>(
                securityQuery.queryKey,
            );
            queryClient.setQueryData<SecurityData>(
                securityQuery.queryKey,
                (data) =>
                    data && {
                        ...data,
                        passkeys: data.passkeys.filter(
                            (passkey) => passkey.id !== id,
                        ),
                    },
            );
            return { previous };
        },
        onError: (error, _id, context) => {
            if (context?.previous) {
                queryClient.setQueryData(
                    securityQuery.queryKey,
                    context.previous,
                );
            }
            toast.error(errorMessage(error, 'Could not remove the passkey'));
        },
        onSettled: () => {
            void queryClient.invalidateQueries({
                queryKey: securityQuery.queryKey,
            });
        },
    });

    const handleRegisterSuccess = () => {
        void queryClient.invalidateQueries({
            queryKey: securityQuery.queryKey,
        });
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
                                remove.mutate(id, { onError })
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
