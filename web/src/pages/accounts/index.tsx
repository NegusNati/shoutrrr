import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

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
    type Account,
    type Capability,
    accountsKeys,
    connectedAccountsQuery,
    useAutoRepost,
    useDeleteAccount,
    useMakeDefault,
    useReconnectOAuth,
    useRefreshXTier,
    useToggleAccount,
} from '@/features/accounts/connected-accounts';

export const ACCOUNT_GRID_CLASS = 'grid grid-cols-1 gap-4 lg:grid-cols-2';

export type AccountsSearch = { success?: string; error?: string };

export default function ConnectedAccounts({
    search,
}: {
    search: AccountsSearch;
}) {
    const { data } = useQuery(connectedAccountsQuery);
    const queryClient = useQueryClient();
    const navigate = useNavigate();

    const accounts = data?.accounts ?? [];
    const capabilities: Capability[] = data?.capabilities ?? [];
    const canManage = data?.canManage ?? false;

    const disabledPlatforms = new Set(
        capabilities.filter((c) => !c.enabled).map((c) => c.platform),
    );

    useEffect(() => {
        document.title = 'Accounts';

        return () => {
            document.title = 'Shoutrrr';
        };
    }, []);

    /**
     * OAuth callbacks land back here with `?success=`/`?error=` query params
     * (set by `Spa::url`). Surface success as a toast and errors as a
     * persistent, dismissible banner (a toast alone is easy to miss for a
     * failed connect), then strip the params so a reload doesn't re-fire.
     */
    const flashedRef = useRef(false);
    useEffect(() => {
        if (flashedRef.current) {
            return;
        }
        flashedRef.current = true;

        if (search.success) {
            toast.success(search.success);
        }
        if (search.success || search.error) {
            void navigate({
                to: '/accounts',
                search: {},
                replace: true,
            });
        }
    }, [search.success, search.error, navigate]);

    const [dismissedError, setDismissedError] = useState<string | null>(null);
    const connectError =
        search.error && search.error !== dismissedError ? search.error : null;

    const toggleMutation = useToggleAccount();
    const autoRepostMutation = useAutoRepost();
    const refreshXTierMutation = useRefreshXTier();
    const makeDefaultMutation = useMakeDefault();
    const deleteMutation = useDeleteAccount();
    const reconnectMutation = useReconnectOAuth();

    const invalidate = () =>
        queryClient.invalidateQueries({ queryKey: accountsKeys.manage });

    const disconnect = (account: Account) => deleteMutation.mutate(account.id);

    const reconnectOAuth = (account: Account) =>
        reconnectMutation.mutate(account.id);

    const toggleEnabled = (account: Account, enabled: boolean) =>
        toggleMutation.mutate({ accountId: account.id, enabled });

    const setAutoRepost = (account: Account, enabled: boolean) =>
        autoRepostMutation.mutate({ accountId: account.id, enabled });

    const refreshXAccountTier = (account: Account) =>
        refreshXTierMutation.mutate(account.id);

    const makeDefault = (account: Account) =>
        makeDefaultMutation.mutate(account.id);

    // A disabled account is neither "connected" nor "needs attention" — it's a
    // third, dormant bucket, so each account lands in exactly one count.
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

            {connectError && (
                <Alert variant="destructive" className="relative pr-10">
                    <CircleAlert />
                    <AlertTitle>Couldn't connect the account</AlertTitle>
                    <AlertDescription>{connectError}</AlertDescription>
                    <button
                        type="button"
                        onClick={() => setDismissedError(search.error ?? null)}
                        aria-label="Dismiss"
                        className="absolute top-3 right-3 text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <X className="size-4" />
                    </button>
                </Alert>
            )}

            {accounts.length === 0 ? (
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
                                onDisconnect={disconnect}
                                onToggle={toggleEnabled}
                                onAutoRepost={setAutoRepost}
                                onRefreshXAccountTier={refreshXAccountTier}
                                onMakeDefault={makeDefault}
                                onReconnected={invalidate}
                                refreshingXAccountTier={
                                    refreshXTierMutation.isPending &&
                                    refreshXTierMutation.variables ===
                                        account.id
                                }
                            />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
