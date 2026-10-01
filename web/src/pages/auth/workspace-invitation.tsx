import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useEffect } from 'react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar, User, Users } from '@/components/ui/icons';
import { Spinner } from '@/components/ui/spinner';
import {
    acceptInvitation,
    publicInvitationQuery,
} from '@/features/auth/invitation';
import { useMe } from '@/features/me/me';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { errorMessage } from '@/lib/api';

export default function WorkspaceInvitation({ token }: { token: string }) {
    useDocumentTitle('Join workspace');
    const navigate = useNavigate();
    const me = useMe();
    const { data, isPending, isError } = useQuery(publicInvitationQuery(token));

    const signedIn = me.data !== undefined;

    // Same as the legacy page: an already-signed-in invitee is accepted
    // straight through — no click needed.
    useEffect(() => {
        if (!signedIn || data === undefined) {
            return;
        }
        acceptInvitation(data.invitation.id)
            .then(() => {
                toast.success(`Joined ${data.invitation.workspace_name}`);
                void navigate({ to: '/dashboard' });
            })
            .catch((error: unknown) => {
                toast.error(
                    errorMessage(error, 'Could not accept the invitation'),
                );
            });
    }, [signedIn, data, navigate]);

    if (isPending || signedIn) {
        return (
            <div className="flex min-h-[200px] items-center justify-center">
                <Spinner className="size-5" />
            </div>
        );
    }

    if (isError || data === undefined) {
        return (
            <div className="space-y-4 text-center">
                <p className="text-sm text-muted-foreground">
                    This invitation is invalid or has expired.
                </p>
                <Button
                    nativeButton={false}
                    variant="outline"
                    className="w-full"
                    render={<Link to="/login" />}
                >
                    Back to sign in
                </Button>
            </div>
        );
    }

    const { invitation, userExists, loginUrl, registerUrl } = data;

    return (
        <div className="space-y-6">
            <div className="rounded-md border p-4">
                <div className="mb-2 flex items-center justify-between gap-2">
                    <span className="flex items-center gap-2 font-medium">
                        <Users className="size-4" />
                        {invitation.workspace_name}
                    </span>
                    <Badge variant="outline" className="capitalize">
                        {invitation.role}
                    </Badge>
                </div>
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <User className="size-4" />
                    Invited by {invitation.inviter_name}
                </p>
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Calendar className="size-4" />
                    Expires{' '}
                    {new Date(invitation.expires_at).toLocaleDateString()}
                </p>
            </div>

            <div className="space-y-3">
                <Button
                    nativeButton={false}
                    className="w-full"
                    render={<a href={loginUrl} />}
                >
                    {userExists
                        ? 'Sign in & join'
                        : 'I already have an account'}
                </Button>
                {!userExists && (
                    <Button
                        nativeButton={false}
                        variant="outline"
                        className="w-full"
                        render={<a href={registerUrl} />}
                    >
                        Create account & join
                    </Button>
                )}
            </div>
        </div>
    );
}
