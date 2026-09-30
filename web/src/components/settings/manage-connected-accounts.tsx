import { useMutation, useQueryClient } from '@tanstack/react-query';

import Heading from '@/components/common/heading';
import ProviderIcon from '@/components/socialite/provider-icon';
import { Button } from '@/components/ui/button';
import { settingsKeys } from '@/features/settings/settings';
import type { Connection, ConnectionsData } from '@/features/settings/settings';
import { apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';

export type { Connection } from '@/features/settings/settings';

type Props = {
    connections: Connection[];
};

export default function ManageConnectedAccounts({ connections }: Props) {
    const queryClient = useQueryClient();

    const disconnect = useMutation({
        mutationFn: (id: string) =>
            apiFetch(endpoints.settingsConnection(id), { method: 'DELETE' }),
        // Disconnecting doesn't remove the provider row — it flips the row back
        // to its "Connect" state, so the optimistic update maps the matching
        // connection rather than removing it.
        onMutate: async (id) => {
            await queryClient.cancelQueries({
                queryKey: settingsKeys.connections,
            });
            const previous = queryClient.getQueryData<ConnectionsData>(
                settingsKeys.connections,
            );
            queryClient.setQueryData<ConnectionsData>(
                settingsKeys.connections,
                (data) =>
                    data === undefined
                        ? data
                        : {
                              ...data,
                              connections: data.connections.map((c) =>
                                  c.id === id
                                      ? { ...c, connected: false, id: null }
                                      : c,
                              ),
                          },
            );

            return { previous };
        },
        onError: (_err, _id, context) => {
            if (context?.previous !== undefined) {
                queryClient.setQueryData(
                    settingsKeys.connections,
                    context.previous,
                );
            }
        },
        onSettled: () =>
            queryClient.invalidateQueries({
                queryKey: settingsKeys.connections,
            }),
    });

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Connected accounts"
                description="Connect a provider to sign in faster"
            />

            <div className="divide-y divide-border overflow-hidden rounded-lg border border-border">
                {connections.map((connection) => {
                    const id = connection.id;

                    return (
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

                        {connection.connected && id !== null ? (
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={disconnect.isPending}
                                onClick={() =>
                                    disconnect.mutate(id)
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
                                        href={`/auth/${connection.provider}/redirect`}
                                    />
                                }
                            >
                                Connect
                            </Button>
                        )}
                    </div>
                    );
                })}
            </div>
        </div>
    );
}
