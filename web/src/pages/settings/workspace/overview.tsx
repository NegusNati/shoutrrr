import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
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
import { Camera, ChevronsUpDown } from '@/components/ui/icons';
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
    updateWorkspace,
    updateWorkspaceTimezone,
    useLeaveWorkspaceContext,
    workspaceOverviewQuery,
    workspaceSettingsKeys,
} from '@/features/settings/workspace-settings';
import { ApiError, getErrorMessage } from '@/lib/api';
import { cn } from '@/lib/utils';

export default function WorkspaceOverview() {
    const { data } = useQuery(workspaceOverviewQuery);
    const queryClient = useQueryClient();
    const confirmAction = useConfirm();
    const leaveContext = useLeaveWorkspaceContext();

    const [processing, setProcessing] = useState(false);
    const [saved, setSaved] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [leaving, setLeaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [selectedPhoto, setSelectedPhoto] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);

    useEffect(() => {
        if (!selectedPhoto) {
            setPhotoPreview(null);

            return;
        }

        const previewUrl = URL.createObjectURL(selectedPhoto);
        setPhotoPreview(previewUrl);

        return () => URL.revokeObjectURL(previewUrl);
    }, [selectedPhoto]);

    if (!data) {
        return null;
    }

    const { workspace, canManage, isOwner, canDelete, deleteDisabledReason } =
        data;

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const payload = new FormData(event.currentTarget);
        if (selectedPhoto) {
            payload.set('photo', selectedPhoto);
        } else {
            payload.delete('photo');
        }

        setProcessing(true);
        setErrors({});
        setSaved(false);

        try {
            await updateWorkspace(payload);
            setSelectedPhoto(null);
            setSaved(true);
            await Promise.all([
                queryClient.invalidateQueries({
                    queryKey: workspaceSettingsKeys.overview,
                }),
                queryClient.invalidateQueries({ queryKey: meQuery.queryKey }),
            ]);
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                setErrors(
                    Object.fromEntries(
                        Object.entries(error.errors).map(([k, v]) => [k, v[0]]),
                    ),
                );
            } else {
                toast.error(
                    getErrorMessage(error, 'Could not save the workspace.'),
                );
            }
        } finally {
            setProcessing(false);
        }
    }

    async function handleDelete() {
        if (!canDelete || deleting) {
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
            await deleteWorkspace();
            await leaveContext();
        } catch (error) {
            toast.error(
                getErrorMessage(error, 'Could not delete the workspace.'),
            );
            setDeleting(false);
        }
    }

    async function handleLeave() {
        const confirmed = await confirmAction({
            title: 'Leave this workspace?',
            description: "You'll lose access until you're invited again.",
            actionLabel: 'Leave workspace',
            destructive: true,
        });

        if (!confirmed) {
            return;
        }

        setLeaving(true);
        try {
            await leaveWorkspace();
            await leaveContext();
        } catch (error) {
            toast.error(
                getErrorMessage(error, 'Could not leave the workspace.'),
            );
            setLeaving(false);
        }
    }

    return (
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
                            src={photoPreview ?? workspace.logo}
                            alt={workspace.name}
                            className="size-16 rounded-md object-cover"
                        />
                        <div className="grid min-w-0 gap-1">
                            <label
                                htmlFor="workspace-photo"
                                aria-disabled={!canManage || processing}
                                className={cn(
                                    buttonVariants(),
                                    'w-fit cursor-pointer aria-disabled:pointer-events-none aria-disabled:opacity-50',
                                )}
                            >
                                <Camera className="size-4" />
                                Choose photo
                            </label>
                            <Input
                                id="workspace-photo"
                                type="file"
                                name="photo"
                                accept="image/*"
                                disabled={!canManage || processing}
                                className="sr-only"
                                onChange={(event) =>
                                    setSelectedPhoto(
                                        event.currentTarget.files?.[0] ?? null,
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
                    <Label htmlFor="workspace-name">Workspace name</Label>
                    <Input
                        id="workspace-name"
                        name="name"
                        autoComplete="organization"
                        defaultValue={workspace.name}
                        disabled={!canManage || processing}
                        placeholder="Workspace name"
                        required
                    />
                    <InputError message={errors.name} />
                </div>

                {canManage && (
                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : 'Save'}
                        </Button>
                        {saved && (
                            <p className="text-sm text-muted-foreground">
                                Saved
                            </p>
                        )}
                    </div>
                )}
            </form>

            <TimezoneSection
                timezone={data.timezone}
                timezones={data.timezones}
                canManage={canManage}
            />

            {!isOwner && (
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
                                You'll lose access until you're invited again.
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
                                {deleting ? 'Deleting...' : 'Delete workspace'}
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
    const [saving, setSaving] = useState(false);
    const dirty = value !== timezone;

    async function onSave() {
        setSaving(true);
        try {
            await updateWorkspaceTimezone(value);
            toast.success('Posting timezone saved.');
            await queryClient.invalidateQueries({
                queryKey: workspaceSettingsKeys.overview,
            });
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not save the timezone.'));
        } finally {
            setSaving(false);
        }
    }

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
                    disabled={!dirty || saving}
                    onClick={onSave}
                >
                    {saving ? 'Saving...' : 'Save'}
                </Button>
            )}
        </div>
    );
}
