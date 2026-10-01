import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import ProviderIcon from '@/components/socialite/provider-icon';
import { Button } from '@/components/ui/button';
import {
    connectionsQuery,
    disconnectConnection,
    type Connection,
    type ConnectionsData,
} from '@/features/user-settings/user-settings';
import { errorMessage } from '@/lib/api';
import { redirect } from '@/routes/auth/socialite';

export default function ManageConnectedAccounts({
    connections,
}: {
    connections: Connection[];
}) {
    const queryClient = useQueryClient();

    const disconnect = useMutation({
        mutationFn: disconnectConnection,
        // Disconnecting doesn't remove the provider row — it flips the row back
        // to its "Connect" state (cleared id, connected: false).
        onMutate: async (id) => {
            await queryClient.cancelQueries({
                queryKey: connectionsQuery.queryKey,
            });
            const previous = queryClient.getQueryData<ConnectionsData>(
                connectionsQuery.queryKey,
            );
            queryClient.setQueryData<ConnectionsData>(
                connectionsQuery.queryKey,
                (data) =>
                    data && {
                        ...data,
                        connections: data.connections.map((connection) =>
                            connection.id === id
                                ? {
                                      ...connection,
                                      connected: false,
                                      id: null,
                                  }
                                : connection,
                        ),
                    },
            );
            return { previous };
        },
        onError: (error, _id, context) => {
            if (context?.previous) {
                queryClient.setQueryData(
                    connectionsQuery.queryKey,
                    context.previous,
                );
            }
            toast.error(
                errorMessage(error, 'Could not disconnect the account.'),
            );
        },
        onSettled: () => {
            void queryClient.invalidateQueries({
                queryKey: connectionsQuery.queryKey,
            });
        },
    });

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Connected accounts"
                description="Connect a provider to sign in faster"
            />

            <div className="divide-y divide-border overflow-hidden rounded-lg border border-border">
                {connections.map((connection) => (
                    <div
                        key={connection.provider}
                        className="flex items-center justify-between p-4"
                    >
                        <div className="flex items-center gap-3">
                            <ProviderIcon
                                provider={connection.provider}
                                className="h-5 w-5"
                            />
                            <span className="font-medium">
                                {connection.label}
                            </span>
                        </div>

                        {connection.connected && connection.id ? (
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={disconnect.isPending}
                                onClick={() =>
                                    disconnect.mutate(connection.id as string)
                                }
                            >
                                Disconnect
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                size="sm"
                                nativeButton={false}
                                render={
                                    <a
                                        href={redirect.url(connection.provider)}
                                    />
                                }
                            >
                                Connect
                            </Button>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
