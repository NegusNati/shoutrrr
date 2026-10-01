import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useSearch } from '@tanstack/react-router';
import { useState } from 'react';
import { toast } from 'sonner';

import BlueskyOAuthController from '@/actions/App/Http/Controllers/ConnectedAccounts/BlueskyOAuthController';
import OAuthConnectionController from '@/actions/App/Http/Controllers/ConnectedAccounts/OAuthConnectionController';
import { AccountCard } from '@/components/accounts/account-card';
import { ConnectButtons } from '@/components/accounts/connect-buttons';
import Heading from '@/components/common/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { CircleAlert, Plug, X } from '@/components/ui/icons';
import {
    accountsQuery,
    disconnectAccount,
    makeDefaultAccount,
    reconnectAccount,
    refreshXAccountTier,
    setAutoRepost,
    toggleAccount,
    type Account,
    type ReconnectPayload,
} from '@/features/accounts/accounts';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { errorMessage } from '@/lib/api';

export const ACCOUNT_GRID_CLASS = 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3';

export function reconnectOAuthUrl(account: Account): string {
    if (account.platform !== 'bluesky') {
        return OAuthConnectionController.redirect.url({
            platform: account.platform,
        });
    }

    return BlueskyOAuthController.redirect.url({
        query: {
            identifier: account.handle.replace(/^@/, ''),
            ...(account.pds_url ? { pds_url: account.pds_url } : {}),
        },
    });
}

export default function AccountsIndexPage() {
    useDocumentTitle('Accounts');

    const queryClient = useQueryClient();
    const { data, isPending } = useQuery(accountsQuery);
    const accounts = data?.accounts ?? [];
    const capabilities = data?.capabilities ?? [];
    const canManage = data?.can_manage ?? false;

    const invalidate = () =>
        queryClient.invalidateQueries({ queryKey: ['accounts'] });

    const [refreshingAccountId, setRefreshingAccountId] = useState<
        string | null
    >(null);
    const [connectError, setConnectError] = useState<string | null>(null);
    const search = useSearch({ strict: false }) as { error?: string };

    // OAuth callbacks redirect back to /accounts (the web route) which then
    // forwards to this page with ?error= — see the legacy routes.
    const bannerError = connectError ?? search.error ?? null;

    const disabledPlatforms = new Set(
        capabilities.filter((c) => !c.enabled).map((c) => c.platform),
    );

    const disconnect = useMutation({
        mutationFn: disconnectAccount,
        onSuccess: () => {
            toast.success('Account disconnected.');
            void invalidate();
        },
        onError: (error) =>
            toast.error(
                errorMessage(error, 'Could not disconnect the account.'),
            ),
    });

    const toggle = useMutation({
        mutationFn: toggleAccount,
        onSuccess: () => void invalidate(),
        onError: (error) =>
            toast.error(errorMessage(error, 'Could not update the account.')),
    });

    const makeDefault = useMutation({
        mutationFn: makeDefaultAccount,
        onSuccess: () => void invalidate(),
        onError: (error) =>
            toast.error(errorMessage(error, 'Could not set the default.')),
    });

    const autoRepost = useMutation({
        mutationFn: ({
            account,
            enabled,
        }: {
            account: Account;
            enabled: boolean;
        }) => setAutoRepost(account.id, enabled),
        onSuccess: () => void invalidate(),
        onError: (error) =>
            toast.error(errorMessage(error, 'Could not update auto-boost.')),
    });

    const refreshTier = useMutation({
        mutationFn: (account: Account) => refreshXAccountTier(account.id),
        onMutate: (account) => setRefreshingAccountId(account.id),
        onSettled: () => setRefreshingAccountId(null),
        onSuccess: () => void invalidate(),
        onError: (error) =>
            toast.error(
                errorMessage(error, 'Could not refresh the X account tier.'),
            ),
    });

    const reconnect = (account: Account, payload: ReconnectPayload) =>
        reconnectAccount(account.id, payload).then(() => {
            toast.success('Account reconnected.');
            void invalidate();
        });

    const reconnectOAuth = (account: Account) => {
        window.location.href = reconnectOAuthUrl(account);
    };

    const connectedCount = accounts.filter(
        (a) => a.status === 'active' && !a.disabled,
    ).length;
    const attentionCount = accounts.filter(
        (a) => a.status !== 'active' && !a.disabled,
    ).length;
    const disabledCount = accounts.filter((a) => a.disabled).length;

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 pt-6 pb-16 sm:px-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <Heading
                    title="Connected accounts"
                    description="Workspace-owned social accounts shared by every member."
                />
                {canManage && (
                    <ConnectButtons
                        capabilities={capabilities}
                        onConnected={invalidate}
                    />
                )}
            </div>

            {bannerError && (
                <Alert variant="destructive" className="relative pr-10">
                    <CircleAlert />
                    <AlertTitle>Couldn't connect the account</AlertTitle>
                    <AlertDescription>{bannerError}</AlertDescription>
                    <button
                        type="button"
                        onClick={() => setConnectError('')}
                        aria-label="Dismiss"
                        className="absolute top-3 right-3 text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <X className="size-4" />
                    </button>
                </Alert>
            )}

            {isPending ? (
                <div className={ACCOUNT_GRID_CLASS}>
                    {[0, 1, 2].map((i) => (
                        <div
                            key={i}
                            className="h-44 animate-pulse rounded-xl border bg-card"
                        />
                    ))}
                </div>
            ) : accounts.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Plug />
                        </EmptyMedia>
                        <EmptyTitle>No accounts connected</EmptyTitle>
                        <EmptyDescription>
                            {canManage
                                ? 'Connect a social account to start scheduling and publishing posts.'
                                : 'Ask an admin to connect one.'}
                        </EmptyDescription>
                    </EmptyHeader>
                    {canManage && (
                        <div className="mt-4 flex justify-center">
                            <ConnectButtons
                                capabilities={capabilities}
                                onConnected={invalidate}
                            />
                        </div>
                    )}
                </Empty>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="flex items-center gap-4 text-[12.5px]">
                        <span className="flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-emerald-500" />
                            <span className="font-medium tabular-nums">
                                {connectedCount}
                            </span>
                            <span className="text-muted-foreground">
                                connected
                            </span>
                        </span>
                        {attentionCount > 0 && (
                            <span className="flex items-center gap-1.5">
                                <span className="size-1.5 rounded-full bg-destructive" />
                                <span className="font-medium text-destructive tabular-nums">
                                    {attentionCount}
                                </span>
                                <span className="text-muted-foreground">
                                    need{attentionCount === 1 ? 's' : ''}{' '}
                                    attention
                                </span>
                            </span>
                        )}
                        {disabledCount > 0 && (
                            <span className="flex items-center gap-1.5">
                                <span className="size-1.5 rounded-full bg-muted-foreground/60" />
                                <span className="font-medium tabular-nums">
                                    {disabledCount}
                                </span>
                                <span className="text-muted-foreground">
                                    disabled
                                </span>
                            </span>
                        )}
                    </div>

                    <div className={ACCOUNT_GRID_CLASS}>
                        {accounts.map((account) => (
                            <AccountCard
                                key={account.id}
                                account={account}
                                canManage={canManage}
                                frozen={disabledPlatforms.has(account.platform)}
                                onReconnectOAuth={reconnectOAuth}
                                onReconnect={reconnect}
                                onDisconnect={(a) => disconnect.mutate(a.id)}
                                onToggle={(a) => toggle.mutate(a.id)}
                                onAutoRepost={(a, enabled) =>
                                    autoRepost.mutate({
                                        account: a,
                                        enabled,
                                    })
                                }
                                onMakeDefault={(a) => makeDefault.mutate(a.id)}
                                onRefreshXAccountTier={(a) =>
                                    refreshTier.mutate(a)
                                }
                                refreshingXAccountTier={
                                    refreshingAccountId === account.id
                                }
                            />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
