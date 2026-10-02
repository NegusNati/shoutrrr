import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
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
import { meQuery } from '@/features/me/me';
import {
    cancelInvitation,
    removeMember,
    transferOwnership,
    updateMemberRole,
    workspaceMembersQuery,
    type Member,
    type MembersData,
} from '@/features/workspace-settings/workspace-settings';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError, errorMessage } from '@/lib/api';
import { removeById, replaceById } from '@/lib/optimistic';

const membersKey = workspaceMembersQuery.queryKey;

/** Optimistically apply `update` to the members query cache; returns previous. */
async function optimisticMembers(
    queryClient: ReturnType<typeof useQueryClient>,
    update: (data: MembersData) => MembersData,
) {
    await queryClient.cancelQueries({ queryKey: membersKey });
    const previous = queryClient.getQueryData<MembersData>(membersKey);
    queryClient.setQueryData<MembersData>(membersKey, (data) =>
        data ? update(data) : data,
    );
    return { previous };
}

export default function WorkspaceMembersPage() {
    useDocumentTitle('Workspace members');

    const queryClient = useQueryClient();
    const me = useMeData();
    const { data, isPending } = useQuery(workspaceMembersQuery);

    const isOwner = me?.workspaces.current?.role === 'owner';
    const workspaceId = me?.workspaces.current?.id ?? '';
    const members = data?.members ?? [];
    const pendingInvitations = data?.pendingInvitations ?? [];
    const canManage = data?.canManage ?? false;
    const availableRoles = data?.availableRoles ?? [];

    const [memberToRemove, setMemberToRemove] = useState<Member | null>(null);
    const [memberToPromote, setMemberToPromote] = useState<Member | null>(null);

    const rollback = (previous: MembersData | undefined) => {
        if (previous) {
            queryClient.setQueryData(membersKey, previous);
        }
    };

    const invalidateMembers = () =>
        queryClient.invalidateQueries({ queryKey: membersKey });

    const updateRole = useMutation({
        mutationFn: ({ id, role }: { id: string; role: string }) =>
            updateMemberRole(id, role),
        onMutate: ({ id, role }) =>
            optimisticMembers(queryClient, (data) => ({
                ...data,
                members: replaceById(data.members, id, (member) => ({
                    ...member,
                    role,
                })),
            })),
        onSuccess: () => toast.success('Member role updated'),
        onError: (error, _vars, context) => {
            rollback(context?.previous);
            toast.error(
                errorMessage(error, 'Could not update the member role'),
            );
        },
        onSettled: invalidateMembers,
    });

    const remove = useMutation({
        mutationFn: removeMember,
        onMutate: (id) =>
            optimisticMembers(queryClient, (data) => ({
                ...data,
                members: removeById(data.members, id),
            })),
        onSuccess: () => {
            toast.success('Member removed');
            setMemberToRemove(null);
        },
        onError: (error, _vars, context) => {
            rollback(context?.previous);
            setMemberToRemove(null);
            toast.error(errorMessage(error, 'Could not remove the member'));
        },
        onSettled: invalidateMembers,
    });

    const cancel = useMutation({
        mutationFn: cancelInvitation,
        onMutate: (id) =>
            optimisticMembers(queryClient, (data) => ({
                ...data,
                pendingInvitations: removeById(data.pendingInvitations, id),
            })),
        onSuccess: () => toast.success('Invitation cancelled'),
        onError: (error, _vars, context) => {
            rollback(context?.previous);
            toast.error(errorMessage(error, 'Could not cancel the invitation'));
        },
        onSettled: invalidateMembers,
    });

    const transfer = useMutation({
        mutationFn: (membershipId: string) =>
            transferOwnership(workspaceId, membershipId),
        // The caller drops from owner to admin, so /me must refetch before
        // role-gated UI (transfer dialog, owner-only actions) re-renders.
        onSuccess: async () => {
            toast.success('Ownership transferred');
            setMemberToPromote(null);
            await queryClient.invalidateQueries({
                queryKey: meQuery.queryKey,
            });
            void invalidateMembers();
        },
        onError: (error) => {
            setMemberToPromote(null);
            toast.error(
                error instanceof ApiError && error.status === 403
                    ? 'Only the workspace owner can transfer ownership.'
                    : errorMessage(error, 'Could not transfer ownership'),
            );
        },
    });

    return (
        <>
            <h1 className="sr-only">Workspace members</h1>

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Members"
                        description="Manage workspace members and their roles"
                    />
                    {canManage && (
                        <InviteMemberDialog availableRoles={availableRoles} />
                    )}
                </div>

                {isPending ? (
                    <MembersSkeleton />
                ) : (
                    <MembersTable
                        members={members}
                        canManage={canManage}
                        isOwner={isOwner}
                        availableRoles={availableRoles}
                        onUpdateRole={(id, role) =>
                            updateRole.mutate({ id, role })
                        }
                        onRemove={setMemberToRemove}
                        onTransfer={setMemberToPromote}
                    />
                )}

                <PendingInvitationsTable
                    invitations={pendingInvitations}
                    canManage={canManage}
                    onCancel={(id) => cancel.mutate(id)}
                />
            </div>

            <RemoveMemberDialog
                member={memberToRemove}
                onClose={() => setMemberToRemove(null)}
                onConfirm={() => {
                    if (memberToRemove) {
                        remove.mutate(memberToRemove.id);
                    }
                }}
            />

            <TransferOwnershipDialog
                member={memberToPromote}
                onClose={() => setMemberToPromote(null)}
                onConfirm={() => {
                    if (memberToPromote) {
                        transfer.mutate(memberToPromote.id);
                    }
                }}
            />
        </>
    );
}
