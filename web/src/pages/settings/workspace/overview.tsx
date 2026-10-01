import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useEffect, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

import { useConfirm } from '@/components/common/confirm-dialog';
import Heading from '@/components/common/heading';
import InputError from '@/components/common/input-error';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { ChevronsUpDown } from '@/components/ui/icons';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { meQuery } from '@/features/me/me';
import {
    deleteWorkspace,
    leaveWorkspace,
    updateTimezone,
    updateWorkspace,
    workspaceOverviewQuery,
    type WorkspaceOverviewData,
} from '@/features/workspace-settings/workspace-settings';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError, errorMessage } from '@/lib/api';
import { fieldString } from '@/lib/forms';
import { cn } from '@/lib/utils';

export default function WorkspaceOverviewPage() {
    useDocumentTitle('Workspace settings');

    const { data } = useQuery(workspaceOverviewQuery);
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const confirmAction = useConfirm();
    const [deleting, setDeleting] = useState(false);
    const [leaving, setLeaving] = useState(false);
    const [selectedPhoto, setSelectedPhoto] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );

    const workspace = data?.workspace;
    const canManage = data?.canManage ?? false;
    const isOwner = data?.isOwner ?? false;
    const canDelete = data?.canDelete ?? false;
    const deleteDisabledReason = data?.deleteDisabledReason ?? null;

    useEffect(() => {
        if (!selectedPhoto) {
            setPhotoPreview(null);

            return;
        }

        const previewUrl = URL.createObjectURL(selectedPhoto);
        setPhotoPreview(previewUrl);

        return () => URL.revokeObjectURL(previewUrl);
    }, [selectedPhoto]);

    const invalidateWorkspaceData = async () => {
        await Promise.all([
            queryClient.invalidateQueries({
                queryKey: workspaceOverviewQuery.queryKey,
            }),
            queryClient.invalidateQueries({ queryKey: meQuery.queryKey }),
        ]);
    };

    const save = useMutation({
        mutationFn: updateWorkspace,
        onSuccess: () => {
            setErrors({});
            setSelectedPhoto(null);
            void invalidateWorkspaceData();
            toast.success('Workspace updated.');
        },
        onError: (error) => {
            if (error instanceof ApiError && error.status === 422) {
                setErrors({
                    name: error.fieldError('name'),
                    photo: error.fieldError('photo'),
                });
            } else {
                toast.error(
                    errorMessage(error, 'Could not update the workspace.'),
                );
            }
        },
    });

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        save.mutate({
            name: fieldString(form, 'name'),
            photo: selectedPhoto,
        });
    }

    /**
     * The legacy lifecycle routes report failure via `back()->withErrors` —
     * a 200 the fetch layer can't distinguish from success — so confirm the
     * leave/delete actually happened by refetching /me.
     */
    async function currentWorkspaceStill(workspaceId: string) {
        const me = await queryClient.fetchQuery(meQuery);
        return me.workspaces.current?.id === workspaceId;
    }

    async function handleLeave() {
        if (!workspace || leaving) {
            return;
        }

        if (!confirm('Leave this workspace?')) {
            return;
        }

        setLeaving(true);
        try {
            await leaveWorkspace(workspace.id);
            if (await currentWorkspaceStill(workspace.id)) {
                toast.error('Could not leave the workspace.');
            } else {
                toast.success('You left the workspace.');
                void navigate({ to: '/dashboard' });
            }
        } catch (error) {
            toast.error(errorMessage(error, 'Could not leave the workspace.'));
        } finally {
            setLeaving(false);
        }
    }

    async function handleDelete() {
        if (!workspace || !canDelete || deleting) {
            return;
        }

        const confirmed = await confirmAction({
            title: 'Delete workspace?',
            description:
                'This permanently deletes the workspace, its members, and its data. This cannot be undone.',
            actionLabel: 'Delete workspace',
            destructive: true,
        });

        if (!confirmed) {
            return;
        }

        setDeleting(true);
        try {
            await deleteWorkspace(workspace.id);
            if (await currentWorkspaceStill(workspace.id)) {
                toast.error('Could not delete the workspace.');
            } else {
                toast.success('Workspace deleted.');
                void navigate({ to: '/dashboard' });
            }
        } catch (error) {
            toast.error(errorMessage(error, 'Could not delete the workspace.'));
        } finally {
            setDeleting(false);
        }
    }

    return (
        <>
            <h1 className="sr-only">Workspace settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Workspace information"
                    description="Update your workspace name and settings"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="workspace-photo">Workspace photo</Label>
                        <div className="flex items-center gap-4">
                            <img
                                src={photoPreview ?? workspace?.logo}
                                alt={workspace?.name}
                                className="size-16 rounded-md object-cover"
                            />
                            <div className="grid min-w-0 gap-1">
                                <label
                                    htmlFor="workspace-photo"
                                    aria-disabled={!canManage || save.isPending}
                                    className={cn(
                                        buttonVariants(),
                                        'w-fit cursor-pointer aria-disabled:pointer-events-none aria-disabled:opacity-50',
                                    )}
                                >
                                    Choose photo
                                </label>
                                <Input
                                    id="workspace-photo"
                                    type="file"
                                    name="photo"
                                    accept="image/*"
                                    disabled={!canManage || save.isPending}
                                    className="sr-only"
                                    onChange={(event) =>
                                        setSelectedPhoto(
                                            event.currentTarget.files?.[0] ??
                                                null,
                                        )
                                    }
                                />
                                <p
                                    className="max-w-56 truncate text-xs text-muted-foreground"
                                    title={selectedPhoto?.name}
                                >
                                    {selectedPhoto
                                        ? `Selected: ${selectedPhoto.name}`
                                        : 'Image up to 2 MB.'}
                                </p>
                            </div>
                        </div>
                        <InputError message={errors.photo} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Workspace name</Label>
                        <Input
                            id="name"
                            name="name"
                            autoComplete="organization"
                            defaultValue={workspace?.name}
                            disabled={!canManage || save.isPending}
                            placeholder="Workspace name"
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    {canManage && (
                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={save.isPending}>
                                {save.isPending ? 'Saving...' : 'Save'}
                            </Button>
                        </div>
                    )}
                </form>

                <TimezoneSection
                    timezone={data?.timezone ?? ''}
                    timezones={data?.timezones ?? []}
                    canManage={canManage}
                />

                {!isOwner && workspace && (
                    <div className="space-y-4">
                        <Heading
                            variant="small"
                            title="Leave workspace"
                            description="Remove yourself from this workspace."
                        />
                        <div className="flex flex-col items-start gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <div>
                                <p className="font-medium">Leave workspace</p>
                                <p className="text-sm text-muted-foreground">
                                    You'll lose access until you're invited
                                    again.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={leaving}
                                onClick={handleLeave}
                            >
                                {leaving ? 'Leaving...' : 'Leave workspace'}
                            </Button>
                        </div>
                    </div>
                )}

                {isOwner && (
                    <div className="space-y-4">
                        <Heading
                            variant="small"
                            title="Danger zone"
                            description="Deleting a workspace is permanent and removes all members and data."
                        />
                        <div className="flex flex-col items-start gap-3 rounded-md border border-destructive/30 p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <div>
                                <p className="font-medium">Delete workspace</p>
                                <p className="text-sm text-muted-foreground">
                                    This action cannot be undone.
                                </p>
                            </div>
                            <div className="flex flex-col items-start gap-2 sm:items-end">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={deleting || !canDelete}
                                    onClick={handleDelete}
                                >
                                    {deleting
                                        ? 'Deleting...'
                                        : 'Delete workspace'}
                                </Button>
                                {deleteDisabledReason && (
                                    <p className="max-w-48 text-xs text-muted-foreground sm:text-right">
                                        {deleteDisabledReason}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function TimezoneSection({
    timezone,
    timezones,
    canManage,
}: {
    timezone: string;
    timezones: string[];
    canManage: boolean;
}) {
    const queryClient = useQueryClient();
    const [value, setValue] = useState(timezone);
    const [open, setOpen] = useState(false);
    const dirty = value !== timezone;

    useEffect(() => {
        setValue(timezone);
    }, [timezone]);

    const save = useMutation({
        mutationFn: updateTimezone,
        onMutate: async (next) => {
            await queryClient.cancelQueries({
                queryKey: workspaceOverviewQuery.queryKey,
            });
            const previous = queryClient.getQueryData<WorkspaceOverviewData>(
                workspaceOverviewQuery.queryKey,
            );
            queryClient.setQueryData<WorkspaceOverviewData>(
                workspaceOverviewQuery.queryKey,
                (data) => data && { ...data, timezone: next },
            );
            return { previous };
        },
        onSuccess: () => toast.success('Posting timezone saved.'),
        onError: (error, _next, context) => {
            if (context?.previous) {
                queryClient.setQueryData(
                    workspaceOverviewQuery.queryKey,
                    context.previous,
                );
            }
            toast.error('Could not save the timezone.');
        },
        onSettled: () => {
            void queryClient.invalidateQueries({
                queryKey: workspaceOverviewQuery.queryKey,
            });
        },
    });

    return (
        <div className="space-y-4">
            <Heading
                variant="small"
                title="Posting timezone"
                description="The timezone your queued posts publish in."
            />
            <div className="grid max-w-xs gap-2">
                <Label htmlFor="posting-timezone">Timezone</Label>
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger
                        render={
                            <Button
                                id="posting-timezone"
                                type="button"
                                variant="outline"
                                role="combobox"
                                aria-expanded={open}
                                disabled={!canManage}
                                className="justify-between font-normal"
                            />
                        }
                    >
                        <span className="truncate">
                            {value || 'Select a timezone'}
                        </span>
                        <ChevronsUpDown className="opacity-50" />
                    </PopoverTrigger>
                    <PopoverContent className="w-(--anchor-width) p-0">
                        <Command>
                            <CommandInput placeholder="Search timezones..." />
                            <CommandList>
                                <CommandEmpty>No timezone found.</CommandEmpty>
                                <CommandGroup>
                                    {timezones.map((tz) => (
                                        <CommandItem
                                            key={tz}
                                            value={tz}
                                            data-checked={value === tz}
                                            onSelect={() => {
                                                setValue(tz);
                                                setOpen(false);
                                            }}
                                        >
                                            {tz}
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
            </div>
            {canManage && (
                <Button
                    type="button"
                    disabled={!dirty || save.isPending}
                    onClick={() => save.mutate(value)}
                >
                    {save.isPending ? 'Saving...' : 'Save'}
                </Button>
            )}
        </div>
    );
}
