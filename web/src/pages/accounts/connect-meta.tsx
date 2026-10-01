import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useState } from 'react';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import { PlatformGlyph } from '@/components/common/platform-glyph';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Plug } from '@/components/ui/icons';
import {
    buildMetaSelection,
    metaConnectQuery,
    submitMetaSelection,
    type SelectionState,
} from '@/features/accounts/accounts';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError, errorMessage } from '@/lib/api';

export default function ConnectMetaPage() {
    useDocumentTitle('Connect Meta');

    const navigate = useNavigate();
    const { data, isPending, error } = useQuery(metaConnectQuery);
    const assets = data?.assets ?? [];

    const [selected, setSelected] = useState<SelectionState>({});
    const [processing, setProcessing] = useState(false);

    const toggle = (assetKey: string, platform: string) => {
        setSelected((prev) => ({
            ...prev,
            [assetKey]: {
                ...prev[assetKey],
                [platform]: !prev[assetKey]?.[platform],
            },
        }));
    };

    const selection = buildMetaSelection(selected);

    const submit = () => {
        setProcessing(true);
        submitMetaSelection(selection)
            .then(() => {
                toast.success(
                    selection.length === 1
                        ? '1 account connected.'
                        : `${selection.length} accounts connected.`,
                );
                void navigate({ to: '/accounts' });
            })
            .catch((err: unknown) => {
                if (err instanceof ApiError && err.status === 404) {
                    toast.error(errorMessage(err, 'Your connection expired.'));
                    void navigate({ to: '/accounts' });
                    return;
                }
                toast.error(
                    errorMessage(err, 'Could not connect the accounts.'),
                );
            })
            .finally(() => setProcessing(false));
    };

    if (isPending) {
        return (
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 px-4 pt-6 pb-16 sm:px-6">
                <div className="h-8 w-64 animate-pulse rounded-md bg-muted" />
                <div className="h-24 animate-pulse rounded-xl border bg-card" />
                <div className="h-24 animate-pulse rounded-xl border bg-card" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 pt-6 pb-16 sm:px-6">
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Plug />
                        </EmptyMedia>
                        <EmptyTitle>Connection expired</EmptyTitle>
                        <EmptyDescription>
                            Your Facebook connection expired. Please try again.
                        </EmptyDescription>
                    </EmptyHeader>
                    <div className="mt-4 flex justify-center">
                        <Button render={<Link to="/accounts" />}>
                            Back to accounts
                        </Button>
                    </div>
                </Empty>
            </div>
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 pt-6 pb-16 sm:px-6">
            <Heading
                title="Choose Pages to connect"
                description="Select which Facebook Pages — and their linked Instagram accounts — to connect to this workspace."
            />

            {assets.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Plug />
                        </EmptyMedia>
                        <EmptyTitle>No Pages found</EmptyTitle>
                        <EmptyDescription>
                            We couldn't find any Facebook Pages on your account.
                            Create a Page on Facebook, then reconnect.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <div className="flex flex-col gap-4">
                    {assets.map((asset) => (
                        <div
                            key={asset.key}
                            className="flex flex-col gap-3 rounded-xl border p-4"
                        >
                            <label className="flex items-center gap-3">
                                <Checkbox
                                    checked={!!selected[asset.key]?.facebook}
                                    onCheckedChange={() =>
                                        toggle(asset.key, 'facebook')
                                    }
                                />
                                <PlatformGlyph
                                    platform="facebook"
                                    size={16}
                                    className="size-4"
                                />
                                <span className="font-medium">
                                    {asset.pageName}
                                </span>
                            </label>

                            {asset.platforms.includes('instagram') && (
                                <label className="ml-7 flex items-center gap-3">
                                    <Checkbox
                                        checked={
                                            !!selected[asset.key]?.instagram
                                        }
                                        onCheckedChange={() =>
                                            toggle(asset.key, 'instagram')
                                        }
                                    />
                                    {asset.igAvatarUrl ? (
                                        <img
                                            src={asset.igAvatarUrl}
                                            alt=""
                                            className="size-5 rounded-full object-cover"
                                        />
                                    ) : (
                                        <PlatformGlyph
                                            platform="instagram"
                                            size={16}
                                            className="size-4"
                                        />
                                    )}
                                    <span className="text-sm text-muted-foreground">
                                        {asset.igUsername
                                            ? `@${asset.igUsername}`
                                            : 'Instagram account'}
                                    </span>
                                </label>
                            )}
                        </div>
                    ))}

                    <Button
                        type="button"
                        onClick={submit}
                        disabled={selection.length === 0 || processing}
                        className="w-full sm:w-auto"
                    >
                        Connect selected
                    </Button>
                </div>
            )}
        </div>
    );
}
