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
    linkedInConnectQuery,
    submitLinkedInSelection,
    type LinkedInSelection,
} from '@/features/accounts/accounts';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError, errorMessage } from '@/lib/api';

export default function ConnectLinkedInPage() {
    useDocumentTitle('Connect LinkedIn');

    const navigate = useNavigate();
    const { data, isPending, error } = useQuery(linkedInConnectQuery);
    const person = data?.person;
    const organizations = data?.organizations ?? [];

    const [personChecked, setPersonChecked] = useState(true);
    const [checkedOrgs, setCheckedOrgs] = useState<Record<string, boolean>>({});
    const [processing, setProcessing] = useState(false);

    const selection: LinkedInSelection[] = [
        ...(personChecked ? [{ type: 'person' as const }] : []),
        ...organizations
            .filter((org) => checkedOrgs[org.id])
            .map((org) => ({ type: 'organization' as const, id: org.id })),
    ];

    const submit = () => {
        setProcessing(true);
        submitLinkedInSelection(selection)
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
                <div className="h-16 animate-pulse rounded-xl border bg-card" />
                <div className="h-16 animate-pulse rounded-xl border bg-card" />
            </div>
        );
    }

    if (error || !person) {
        return (
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 pt-6 pb-16 sm:px-6">
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Plug />
                        </EmptyMedia>
                        <EmptyTitle>Connection expired</EmptyTitle>
                        <EmptyDescription>
                            Your LinkedIn connection expired. Please try again.
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
                title="Choose what to connect"
                description="Connect your personal LinkedIn profile and any Pages you administer to this workspace."
            />
            <div className="flex flex-col gap-4">
                <label className="flex items-center gap-3 rounded-xl border p-4">
                    <Checkbox
                        checked={personChecked}
                        onCheckedChange={(c) => setPersonChecked(c === true)}
                    />
                    <PlatformGlyph
                        platform="linkedin"
                        size={16}
                        className="size-4"
                    />
                    <span className="font-medium">
                        {person.displayName ?? person.handle}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        Personal profile
                    </span>
                </label>

                {organizations.map((org) => (
                    <label
                        key={org.id}
                        className="flex items-center gap-3 rounded-xl border p-4"
                    >
                        <Checkbox
                            checked={!!checkedOrgs[org.id]}
                            onCheckedChange={(c) =>
                                setCheckedOrgs((prev) => ({
                                    ...prev,
                                    [org.id]: c === true,
                                }))
                            }
                        />
                        <PlatformGlyph
                            platform="linkedin"
                            size={16}
                            className="size-4"
                        />
                        <span className="font-medium">{org.name}</span>
                        <span className="text-sm text-muted-foreground">
                            Page
                        </span>
                    </label>
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
        </div>
    );
}
