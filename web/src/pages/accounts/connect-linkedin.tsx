import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import { PlatformGlyph } from '@/components/common/platform-glyph';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { CircleAlert } from '@/components/ui/icons';
import {
    type LinkedInSelection,
    accountsKeys,
    linkedinPickerQuery,
    storeLinkedinSelection,
} from '@/features/accounts/connected-accounts';
import { ApiError, getErrorMessage } from '@/lib/api';

export default function ConnectLinkedIn() {
    const { data, error } = useQuery(linkedinPickerQuery);
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const [personChecked, setPersonChecked] = useState(true);
    const [checkedOrgs, setCheckedOrgs] = useState<Record<string, boolean>>({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        document.title = 'Connect LinkedIn';

        return () => {
            document.title = 'Shoutrrr';
        };
    }, []);

    const person = data?.person;
    const organizations = data?.organizations ?? [];

    const selection: LinkedInSelection[] = [
        ...(personChecked && person ? [{ type: 'person' as const }] : []),
        ...organizations
            .filter((org) => checkedOrgs[org.id])
            .map((org) => ({ type: 'organization' as const, id: org.id })),
    ];

    const submit = async () => {
        setProcessing(true);
        try {
            const result = await storeLinkedinSelection(selection);
            await queryClient.invalidateQueries({
                queryKey: accountsKeys.manage,
            });
            toast.success(
                `Connected ${result.connected} account${result.connected === 1 ? '' : 's'}.`,
            );
            await navigate({ to: '/accounts' });
        } catch (submitError) {
            toast.error(
                getErrorMessage(
                    submitError,
                    'Could not connect the LinkedIn accounts.',
                ),
            );
        } finally {
            setProcessing(false);
        }
    };

    const missingStash = error instanceof ApiError && error.status === 404;

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 pt-6 pb-16 sm:px-6">
            <Heading
                title="Choose what to connect"
                description="Connect your personal LinkedIn profile and any Pages you administer to this workspace."
            />

            {missingStash || !person ? (
                <Alert variant="destructive">
                    <CircleAlert />
                    <AlertTitle>Connection expired</AlertTitle>
                    <AlertDescription>
                        The LinkedIn sign-in session expired.{' '}
                        <a
                            href="/accounts/connect/linkedin"
                            className="underline"
                        >
                            Start the LinkedIn connection again
                        </a>
                        .
                    </AlertDescription>
                </Alert>
            ) : (
                <div className="flex flex-col gap-4">
                    <label className="flex items-center gap-3 rounded-xl border p-4">
                        <Checkbox
                            checked={personChecked}
                            onCheckedChange={(c) =>
                                setPersonChecked(c === true)
                            }
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
            )}
        </div>
    );
}
