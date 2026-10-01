import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import InviteMemberDialog from '@/components/settings/invite-member-dialog';
import MembersTable from '@/components/settings/members-table';
import PendingInvitationsTable from '@/components/settings/pending-invitations-table';
import RemoveMemberDialog from '@/components/settings/remove-member-dialog';
import TransferOwnershipDialog from '@/components/settings/transfer-ownership-dialog';
import { MembersSkeleton } from '@/components/skeletons/members-skeleton';
import { useMeData } from '@/features/me/me';
import type { Member } from '@/features/settings/workspace-settings';
import {
    workspaceMembersQuery,
    workspaceSettingsKeys,
} from '@/features/settings/workspace-settings';
import { apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { queryClient } from '@/lib/query-client';

export default function WorkspaceMembers() {
    const me = useMeData();
    const isOwner = me?.workspaces.current?.role === 'owner';
    const { data, isPending } = useQuery(workspaceMembersQuery);

    const [memberToRemove, setMemberToRemove] = useState<Member | null>(null);
    const [memberToPromote, setMemberToPromote] = useState<Member | null>(null);

    const invalidate = () =>
        queryClient.invalidateQueries({
            queryKey: workspaceSettingsKeys.members,
        });

    const updateRole = useMutation({
        mutationFn: ({ memberId, role }: { memberId: string; role: string }) =>
            apiFetch(endpoints.settingsWorkspaceMember(memberId), {
                method: 'PATCH',
                body: { role },
            }),
        onSuccess: () => {
            void invalidate();
            toast.success('Member role updated');
        },
        onError: (err) => {
            toast.error(getErrorMessage(err, 'Could not update the role.'));
        },
    });

    const removeMember = useMutation({
        mutationFn: (memberId: string) =>
            apiFetch(endpoints.settingsWorkspaceMember(memberId), {
                method: 'DELETE',
            }),
        onSuccess: () => {
            void invalidate();
            toast.success('Member removed');
        },
        onSettled: () => setMemberToRemove(null),
        onError: (err) => {
            toast.error(getErrorMessage(err, 'Could not remove the member.'));
        },
    });

    const transferOwnership = useMutation({
        mutationFn: (membershipId: string) =>
            apiFetch(endpoints.settingsWorkspaceTransfer, {
                method: 'POST',
                body: { membership_id: membershipId },
            }),
        onSuccess: () => {
            void invalidate();
            void queryClient.invalidateQueries({ queryKey: ['me'] });
            toast.success('Ownership transferred');
        },
        onSettled: () => setMemberToPromote(null),
        onError: (err) => {
            toast.error(
                getErrorMessage(err, 'Could not transfer ownership.'),
            );
        },
    });

    const cancelInvitation = useMutation({
        mutationFn: (invitationId: string) =>
            apiFetch(endpoints.settingsWorkspaceInvitation(invitationId), {
                method: 'DELETE',
            }),
        onSuccess: () => {
            void invalidate();
            toast.success('Invitation cancelled');
        },
        onError: (err) => {
            toast.error(
                getErrorMessage(err, 'Could not cancel the invitation.'),
            );
        },
    });

    if (!data) {
        return isPending ? <MembersSkeleton /> : null;
    }

    return (
        <>
            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Members"
                        description="Manage workspace members and their roles"
                    />
                    {data.canManage && (
                        <InviteMemberDialog
                            availableRoles={data.availableRoles}
                        />
                    )}
                </div>

                <MembersTable
                    members={data.members}
                    canManage={data.canManage}
                    isOwner={isOwner}
                    availableRoles={data.availableRoles}
                    onUpdateRole={(memberId, role) =>
                        updateRole.mutate({ memberId, role })
                    }
                    onRemove={setMemberToRemove}
                    onTransfer={setMemberToPromote}
                />

                <PendingInvitationsTable
                    invitations={data.pendingInvitations}
                    canManage={data.canManage}
                    onCancel={(id) => cancelInvitation.mutate(id)}
                />
            </div>

            <RemoveMemberDialog
                member={memberToRemove}
                onClose={() => setMemberToRemove(null)}
                onConfirm={() => {
                    if (memberToRemove) {
                        removeMember.mutate(memberToRemove.id);
                    }
                }}
            />

            <TransferOwnershipDialog
                member={memberToPromote}
                onClose={() => setMemberToPromote(null)}
                onConfirm={() => {
                    if (memberToPromote) {
                        transferOwnership.mutate(memberToPromote.id);
                    }
                }}
            />
        </>
    );
}
