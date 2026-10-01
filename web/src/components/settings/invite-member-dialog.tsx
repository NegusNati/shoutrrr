import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
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
import {
    inviteMember,
    workspaceMembersQuery,
} from '@/features/workspace-settings/workspace-settings';
import { ApiError, errorMessage } from '@/lib/api';
import { fieldString } from '@/lib/forms';

import { roleIcon } from './member-role-helpers';

export default function InviteMemberDialog({
    availableRoles,
}: {
    availableRoles: string[];
}) {
    const queryClient = useQueryClient();
    const [open, setOpen] = useState(false);
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );

    const roleItems = availableRoles.map((role) => ({
        value: role,
        label: (
            <span className="flex items-center gap-2">
                {roleIcon(role)}
                <span className="capitalize">{role}</span>
            </span>
        ),
    }));

    const invite = useMutation({
        mutationFn: inviteMember,
        onSuccess: () => {
            setErrors({});
            toast.success('Invitation sent');
            setOpen(false);
            void queryClient.invalidateQueries({
                queryKey: workspaceMembersQuery.queryKey,
            });
        },
        onError: (error) => {
            if (error instanceof ApiError && error.status === 422) {
                setErrors({
                    email: error.fieldError('email'),
                    role: error.fieldError('role'),
                });
            } else {
                toast.error(
                    errorMessage(error, 'Could not send the invitation'),
                );
            }
        },
    });

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        invite.mutate({
            email: fieldString(form, 'email'),
            role: fieldString(form, 'role') || 'member',
        });
    }

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
                <form onSubmit={handleSubmit}>
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
                            <InputError message={errors.email} />
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
                            <InputError message={errors.role} />
                        </div>
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
