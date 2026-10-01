import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';

import { useConfirm } from '@/components/common/confirm-dialog';
import CreateApiKeyDialog from '@/components/settings/create-api-key-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Check, Copy, KeyRound, MoreHorizontal } from '@/components/ui/icons';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ApiKey } from '@/features/settings/workspace-settings';
import {
    workspaceApiKeysQuery,
    workspaceSettingsKeys,
} from '@/features/settings/workspace-settings';
import { apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { dayjs } from '@/lib/datetime/dayjs';
import { queryClient } from '@/lib/query-client';

function expiryMeta(iso: string | null): { text: string; expired: boolean } {
    if (!iso) {
        return { text: 'Never expires', expired: false };
    }
    const date = dayjs(iso);
    const expired = date.isBefore(dayjs());
    return {
        text: `${expired ? 'Expired' : 'Expires'} ${date.format('MMM D, YYYY')}`,
        expired,
    };
}

function NewKeyReveal({ token }: { token: string }) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        if (!navigator.clipboard) {
            toast.error(
                'Copy is unavailable here — select the key and copy it manually.',
            );
            return;
        }
        try {
            await navigator.clipboard.writeText(token);
            setCopied(true);
            toast.success('Copied to clipboard');
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error('Copy failed — select the key and copy it manually.');
        }
    }

    return (
        <Card className="border-primary/40 bg-primary/5">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <KeyRound className="size-4 text-primary" />
                    API key created
                </CardTitle>
                <CardDescription>
                    Copy it now and store it somewhere safe — this is the only
                    time you&apos;ll see the full key.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex items-center gap-2">
                <Input
                    readOnly
                    value={token}
                    aria-label="New API key"
                    onFocus={(event) => event.target.select()}
                    className="font-mono text-xs"
                />
                <Button
                    type="button"
                    variant="outline"
                    className="shrink-0"
                    onClick={copy}
                >
                    {copied ? (
                        <Check className="size-4" />
                    ) : (
                        <Copy className="size-4" />
                    )}
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </CardContent>
        </Card>
    );
}

export default function WorkspaceApiKeys() {
    const confirm = useConfirm();
    const { data } = useQuery(workspaceApiKeysQuery);
    const [newKey, setNewKey] = useState<string | null>(null);

    const revoke = useMutation({
        mutationFn: (keyId: string) =>
            apiFetch(endpoints.settingsWorkspaceApiKey(keyId), {
                method: 'DELETE',
            }),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: workspaceSettingsKeys.apiKeys,
            });
            toast.success('API key revoked');
        },
        onError: (err) => {
            toast.error(getErrorMessage(err, 'Could not revoke the API key.'));
        },
    });

    async function revokeKey(key: ApiKey) {
        const confirmed = await confirm({
            title: `Revoke “${key.name}”?`,
            description:
                'Anything using this key loses access immediately. This cannot be undone.',
            actionLabel: 'Revoke key',
            destructive: true,
        });

        if (confirmed) {
            revoke.mutate(key.id);
        }
    }

    if (!data) {
        return null;
    }

    const apiKeys = data.apiKeys;

    return (
        <div className="space-y-6">
            {newKey && <NewKeyReveal token={newKey} />}

            <Card>
                <CardHeader>
                    <CardTitle>API keys</CardTitle>
                    <CardDescription>
                        Call the Shoutrrr API from scripts, cron jobs, and
                        integrations. Each key acts on this workspace only.
                    </CardDescription>
                    {apiKeys.length > 0 && (
                        <CardAction>
                            <CreateApiKeyDialog onCreated={setNewKey} />
                        </CardAction>
                    )}
                </CardHeader>
                <CardContent>
                    {apiKeys.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <KeyRound />
                                </EmptyMedia>
                                <EmptyTitle>No API keys yet</EmptyTitle>
                                <EmptyDescription>
                                    Create a key to call the Shoutrrr API from
                                    your own tools.
                                </EmptyDescription>
                            </EmptyHeader>
                            <CreateApiKeyDialog onCreated={setNewKey} />
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Access</TableHead>
                                    <TableHead>Last used</TableHead>
                                    <TableHead className="w-12 text-right">
                                        <span className="sr-only">
                                            Actions
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {apiKeys.map((key) => {
                                    const expiry = expiryMeta(key.expires_at);
                                    return (
                                        <TableRow key={key.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {key.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    …{key.last_four} ·{' '}
                                                    <span
                                                        className={
                                                            expiry.expired
                                                                ? 'text-destructive'
                                                                : undefined
                                                        }
                                                    >
                                                        {expiry.text}
                                                    </span>
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {key.scope === 'write' ? (
                                                    <Badge>
                                                        Read &amp; write
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        Read
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {key.last_used_at
                                                    ? dayjs(
                                                          key.last_used_at,
                                                      ).fromNow()
                                                    : 'Never'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        render={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Actions for ${key.name}`}
                                                            />
                                                        }
                                                    >
                                                        <MoreHorizontal className="size-4" />
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onClick={() =>
                                                                void revokeKey(
                                                                    key,
                                                                )
                                                            }
                                                        >
                                                            Revoke key
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
