import { useMutation, useQuery } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

import { useConfirm } from '@/components/common/confirm-dialog';
import Heading from '@/components/common/heading';
import InputError from '@/components/common/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Camera, Trash2 } from '@/components/ui/icons';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useMeData } from '@/features/me/me';
import {
    workspaceSettingsKeys,
    workspaceSettingsQuery,
} from '@/features/settings/workspace-settings';
import { apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { fieldString } from '@/lib/forms';
import { queryClient } from '@/lib/query-client';

function WorkspacePhotoSection({
    canManage,
    logo,
    name,
}: {
    canManage: boolean;
    logo: string;
    name: string;
}) {
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const upload = useMutation({
        mutationFn: (photo: File) => {
            const data = new FormData();
            data.set('_method', 'PATCH');
            data.set('name', name);
            data.set('photo', photo);
            return apiFetch(endpoints.settingsWorkspace, {
                method: 'POST',
                body: data,
            });
        },
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: workspaceSettingsKeys.overview,
            });
            void queryClient.invalidateQueries({ queryKey: ['me'] });
            setPreview(null);
            setError(null);
            toast.success('Workspace logo updated');
        },
        onError: (err) => {
            setError(getErrorMessage(err, 'Could not update the workspace logo.'));
        },
    });

    return (
        <div className="space-y-4">
            <Heading variant="small"
                title="Workspace logo"
                description="Show a logo for the workspace in the navigation"
            />
            <div className="flex items-center gap-4">
                <div className="group relative">
                    <Avatar className="size-20">
                        <AvatarImage src={preview ?? logo} />
                        <AvatarFallback className="text-xl">
                            {name.charAt(0)}
                        </AvatarFallback>
                    </Avatar>
                    {canManage && (
                        <button
                            type="button"
                            aria-label="Change workspace logo"
                            className="absolute inset-0 flex items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition-opacity group-hover:opacity-100"
                            onClick={() => fileInputRef.current?.click()}
                        >
                            <Camera className="size-5" />
                        </button>
                    )}
                </div>
                {canManage && (
                    <p className="text-xs text-muted-foreground">
                        Click the logo to upload a new one.
                    </p>
                )}
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/*"
                    hidden
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (!file) {
                            return;
                        }
                        if (preview) {
                            URL.revokeObjectURL(preview);
                        }
                        setPreview(URL.createObjectURL(file));
                        upload.mutate(file);
                        e.target.value = '';
                    }}
                />
            </div>
            <InputError message={error ?? undefined} />
        </div>
    );
}

export default function WorkspaceSettingsOverview() {
    const me = useMeData();
    const navigate = useNavigate();
    const confirm = useConfirm();
    const { data } = useQuery(workspaceSettingsQuery);

    const updateName = useMutation({
        mutationFn: (name: string) =>
            apiFetch(endpoints.settingsWorkspace, {
                method: 'PATCH',
                body: { name },
            }),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: workspaceSettingsKeys.overview,
            });
            void queryClient.invalidateQueries({ queryKey: ['me'] });
            toast.success('Workspace updated');
        },
        onError: (err) => {
            toast.error(getErrorMessage(err, 'Could not update the workspace.'));
        },
    });

    const updateTimezone = useMutation({
        mutationFn: (timezone: string) =>
            apiFetch(endpoints.settingsWorkspaceTimezone, {
                method: 'PUT',
                body: { timezone },
            }),
        onSuccess: () => {
            toast.success('Timezone updated');
        },
        onError: (err) => {
            toast.error(getErrorMessage(err, 'Could not update the timezone.'));
        },
    });

    const leave = useMutation({
        mutationFn: () =>
            apiFetch<{ left: boolean; next_workspace_id: string }>(
                endpoints.settingsWorkspaceLeave,
                { method: 'POST' },
            ),
        onSuccess: async () => {
            // Server already switched the session to next_workspace_id.
            await queryClient.invalidateQueries();
            toast.success('You left the workspace');
            void navigate({ to: '/settings/profile' });
        },
        onError: (err) => {
            toast.error(getErrorMessage(err, 'Could not leave the workspace.'));
        },
    });

    const destroy = useMutation({
        mutationFn: () =>
            apiFetch<{ deleted: boolean; next_workspace_id: string }>(
                endpoints.settingsWorkspace,
                { method: 'DELETE' },
            ),
        onSuccess: async () => {
            // Server already switched the session to next_workspace_id.
            await queryClient.invalidateQueries();
            toast.success('Workspace deleted');
            void navigate({ to: '/settings/profile' });
        },
        onError: (err) => {
            toast.error(getErrorMessage(err, 'Could not delete the workspace.'));
        },
    });

    if (!data || !me) {
        return null;
    }

    const { workspace } = data;

    const onSubmitName = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        updateName.mutate(fieldString(new FormData(e.currentTarget), 'name'));
    };

    const onLeave = async () => {
        const confirmed = await confirm({
            title: 'Leave workspace?',
            description: `You will lose access to ${workspace.name} until someone invites you back.`,
            actionLabel: 'Leave workspace',
            destructive: true,
        });
        if (confirmed) {
            leave.mutate();
        }
    };

    const onDelete = async () => {
        const confirmed = await confirm({
            title: `Delete ${workspace.name}?`,
            description:
                'This permanently deletes the workspace, its posts, and removes every member. This cannot be undone.',
            actionLabel: 'Delete workspace',
            destructive: true,
        });
        if (confirmed) {
            destroy.mutate();
        }
    };

    return (
        <>
            <WorkspacePhotoSection
                canManage={data.canManage}
                logo={workspace.logo}
                name={workspace.name}
            />

            {data.canManage ? (
                <form onSubmit={onSubmitName}>
                    <div className="space-y-4">
                        <Heading variant="small"
                            title="Workspace name"
                            description="The name your team sees in the workspace picker"
                        />
                        <div className="flex gap-2">
                            <Input
                                name="name"
                                defaultValue={workspace.name}
                                required
                            />
                            <Button
                                type="submit"
                                disabled={updateName.isPending}
                            >
                                {updateName.isPending ? 'Saving…' : 'Save'}
                            </Button>
                        </div>
                    </div>
                </form>
            ) : (
                <div className="space-y-4">
                    <Heading variant="small"
                        title="Workspace name"
                        description="The name your team sees in the workspace picker"
                    />
                    <p>{workspace.name}</p>
                </div>
            )}

            {data.canManage && (
                <div className="space-y-4">
                    <Heading variant="small"
                        title="Timezone"
                        description="Used to pick the next available slot and to display scheduled times"
                    />
                    <Select
                        defaultValue={data.timezone}
                        onValueChange={(value) => {
                            if (value !== null) {
                                updateTimezone.mutate(value);
                            }
                        }}
                    >
                        <SelectTrigger className="w-full max-w-sm">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {data.timezones.map((timezone) => (
                                <SelectItem key={timezone} value={timezone}>
                                    {timezone}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            <div className="space-y-4">
                <Heading variant="small"
                    title="Leave workspace"
                    description="Remove yourself from this workspace"
                />
                <Button
                    variant="outline"
                    onClick={() => void onLeave()}
                    disabled={leave.isPending}
                >
                    Leave workspace
                </Button>
            </div>

            <div className="space-y-4">
                <Heading variant="small"
                    title="Delete workspace"
                    description="Permanently delete this workspace and its data"
                />
                <Card className="border-destructive/30">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Delete {workspace.name}
                        </CardTitle>
                        <CardDescription>
                            This deletes all posts, media, and analytics for the
                            workspace and removes every member.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {data.canDelete ? (
                            <Button
                                variant="destructive"
                                onClick={() => void onDelete()}
                                disabled={destroy.isPending}
                            >
                                <Trash2 className="size-4" />
                                {destroy.isPending
                                    ? 'Deleting…'
                                    : 'Delete workspace'}
                            </Button>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {data.deleteDisabledReason}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
