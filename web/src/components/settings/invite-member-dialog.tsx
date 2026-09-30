import { useQueryClient } from '@tanstack/react-query';
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
import { ApiError, apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { fieldString } from '@/lib/forms';

import { roleIcon } from './member-role-helpers';

export default function InviteMemberDialog({
    availableRoles,
}: {
    availableRoles: string[];
}) {
    const queryClient = useQueryClient();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const formId = 'invite-member-form';

    const roleItems = availableRoles.map((role) => ({
        value: role,
        label: (
            <span className="flex items-center gap-2">
                {roleIcon(role)}
                <span className="capitalize">{role}</span>
            </span>
        ),
    }));

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);

        setProcessing(true);
        setErrors({});

        try {
            await apiFetch(endpoints.settingsWorkspaceInvitations, {
                method: 'POST',
                body: {
                    email: fieldString(data, 'email'),
                    role: fieldString(data, 'role') || 'member',
                },
            });
            toast.success('Invitation sent');
            setOpen(false);
            await queryClient.invalidateQueries({
                queryKey: workspaceSettingsKeys.members,
            });
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                setErrors(
                    Object.fromEntries(
                        Object.entries(error.errors).map(([k, v]) => [k, v[0]]),
                    ),
                );
            } else {
                toast.error(
                    getErrorMessage(error, 'Could not send the invitation.'),
                );
            }
        } finally {
            setProcessing(false);
        }
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
                <form id={formId} onSubmit={handleSubmit}>
                    <div className="space-y-4 py-2">
                        <div className="grid gap-2">
                            <Label htmlFor="invite-email">Email</Label>
                            <Input
                                id="invite-email"
                                name="email"
                                type="email"
                                placeholder="user@example.com"
                                required
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="invite-role">Role</Label>
                            <Select
                                name="role"
                                defaultValue="member"
                                items={roleItems}
                            >
                                <SelectTrigger id="invite-role">
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
                </form>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        Cancel
                    </Button>
                    <Button type="submit" form={formId} disabled={processing}>
                        {processing ? 'Sending...' : 'Send invitation'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
