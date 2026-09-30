import { useQuery } from '@tanstack/react-query';
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
import {
    transferOwnership,
    useCancelInvitation,
    useRemoveMember,
    useUpdateMemberRole,
    workspaceMembersQuery,
    type Member,
} from '@/features/settings/workspace-settings';
import { getErrorMessage } from '@/lib/api';
import { queryClient } from '@/lib/query-client';

export default function WorkspaceMembers() {
    const { data, isPending } = useQuery(workspaceMembersQuery);
    const me = useMeData();
    const isOwner = me?.workspaces.current?.role === 'owner';

    const updateRole = useUpdateMemberRole();
    const removeMember = useRemoveMember();
    const cancelInvitation = useCancelInvitation();

    const [memberToRemove, setMemberToRemove] = useState<Member | null>(null);
    const [memberToPromote, setMemberToPromote] = useState<Member | null>(null);

    const handleUpdateRole = (memberId: string, role: string) => {
        updateRole.mutate({ memberId, role });
    };

    const confirmRemove = () => {
        if (!memberToRemove) {
            return;
        }

        removeMember.mutate(memberToRemove.id, {
            onSettled: () => setMemberToRemove(null),
        });
    };

    const confirmTransfer = async () => {
        if (!memberToPromote) {
            return;
        }

        try {
            await transferOwnership(memberToPromote.id);
            toast.success('Ownership transferred');
            // The signed-in user's role changed too — refresh the whole bootstrap.
            await queryClient.invalidateQueries();
        } catch (error) {
            toast.error(
                getErrorMessage(error, 'Could not transfer ownership.'),
            );
        } finally {
            setMemberToPromote(null);
        }
    };

    const handleCancelInvitation = (invitationId: string) => {
        cancelInvitation.mutate(invitationId);
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between gap-4">
                <Heading
                    variant="small"
                    title="Members"
                    description="Manage workspace members and their roles"
                />
                {data?.canManage && (
                    <InviteMemberDialog availableRoles={data.availableRoles} />
                )}
            </div>

            {isPending || !data ? (
                <MembersSkeleton />
            ) : (
                <MembersTable
                    members={data.members}
                    canManage={data.canManage}
                    isOwner={isOwner}
                    availableRoles={data.availableRoles}
                    onUpdateRole={handleUpdateRole}
                    onRemove={setMemberToRemove}
                    onTransfer={setMemberToPromote}
                />
            )}

            {data && (
                <PendingInvitationsTable
                    invitations={data.pendingInvitations}
                    canManage={data.canManage}
                    onCancel={handleCancelInvitation}
                />
            )}

            <RemoveMemberDialog
                member={memberToRemove}
                onClose={() => setMemberToRemove(null)}
                onConfirm={confirmRemove}
            />

            <TransferOwnershipDialog
                member={memberToPromote}
                onClose={() => setMemberToPromote(null)}
                onConfirm={confirmTransfer}
            />
        </div>
    );
}
