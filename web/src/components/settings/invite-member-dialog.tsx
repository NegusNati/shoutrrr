import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';

import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { UserPlus } from '@/components/ui/icons';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { workspaceSettingsKeys } from '@/features/settings/workspace-settings';
import { apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { fieldString } from '@/lib/forms';
import { queryClient } from '@/lib/query-client';

import { roleIcon } from './member-role-helpers';

export default function InviteMemberDialog({
    availableRoles,
}: {
    availableRoles: string[];
}) {
    const [open, setOpen] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const invite = useMutation({
        mutationFn: (body: { email: string; role: string }) =>
            apiFetch(endpoints.settingsWorkspaceInvite, {
                method: 'POST',
                body,
            }),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: workspaceSettingsKeys.members,
            });
            toast.success('Invitation sent');
            setOpen(false);
            setError(null);
        },
        onError: (err) => {
            setError(getErrorMessage(err, 'Could not send the invitation.'));
        },
    });

    const onSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const data = new FormData(e.currentTarget);
        invite.mutate({
            email: fieldString(data, 'email'),
            role: fieldString(data, 'role'),
        });
    };

    const roleItems = availableRoles.map((role) => ({
        value: role,
        label: (
            <span className="flex items-center gap-2">
                {roleIcon(role)}
                <span className="capitalize">{role}</span>
            </span>
        ),
    }));

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button />}>
                <UserPlus className="size-4" />
                Invite member
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite a new member</DialogTitle>
                    <DialogDescription>
                        Send an invitation to join this workspace.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit}>
                    <div className="space-y-4 py-2">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                placeholder="user@example.com"
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="role">Role</Label>
                            <Select
                                name="role"
                                defaultValue="member"
                                items={roleItems}
                            >
                                <SelectTrigger id="role">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roleItems.map((item) => (
                                        <SelectItem
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <InputError message={error ?? undefined} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={invite.isPending}>
                            {invite.isPending
                                ? 'Sending...'
                                : 'Send invitation'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
