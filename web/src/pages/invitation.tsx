import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar, User, Users } from '@/components/ui/icons';
import { Spinner } from '@/components/ui/spinner';
import {
    invitationQuery,
    useAcceptInvitation,
    type InvitationView,
} from '@/features/invitations/invitations';
import { meQuery } from '@/features/me/me';
import { ApiError, getErrorMessage } from '@/lib/api';
import AuthLayout from '@/layouts/auth-layout';

/**
 * Signed-in visitors auto-accept — the same behavior the legacy web route
 * had. Fires once per invitation id; on failure we surface the API message
 * (email mismatch 404s, revoked invites 422) instead of looping.
 */
function AutoAccept({ invitation }: { invitation: InvitationView }) {
    const accept = useAcceptInvitation();
    const navigate = useNavigate();
    const fired = useRef(false);

    useEffect(() => {
        if (fired.current) {
            return;
        }
        fired.current = true;

        accept.mutate(invitation.id, {
            onSuccess: (data) => {
                toast.success(data.message);
                void navigate({ to: '/dashboard' });
            },
            onError: (error) => {
                toast.error(getErrorMessage(error, 'Could not accept this invitation.'));
                void navigate({ to: '/dashboard' });
            },
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [invitation.id]);

    return (
        <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
            <Spinner />
            Joining {invitation.workspace_name}…
        </div>
    );
}

export default function WorkspaceInvitationPage({ token }: { token: string }) {
    const invitation = useQuery(invitationQuery(token));
    // Guests get a 401 here — that's the "not signed in" signal, not an error.
    const me = useQuery(meQuery);
    const signedIn = me.data !== undefined;

    useEffect(() => {
        document.title = "You're invited — Shoutrrr";

        return () => {
            document.title = 'Shoutrrr';
        };
    }, []);

    if (invitation.isPending) {
        return (
            <AuthLayout
                title="You're invited"
                description="Loading your invitation…"
                brandText="Shoutrrr"
            >
                <div className="flex justify-center">
                    <Spinner />
                </div>
            </AuthLayout>
        );
    }

    if (invitation.isError) {
        const gone =
            invitation.error instanceof ApiError &&
            invitation.error.status === 404;

        return (
            <AuthLayout
                title="Invitation unavailable"
                description={
                    gone
                        ? 'This invitation link is invalid or has expired.'
                        : getErrorMessage(invitation.error)
                }
                brandText="Shoutrrr"
            >
                <Button nativeButton={false} className="w-full" render={<Link to="/login" />}>
                    Go to sign in
                </Button>
            </AuthLayout>
        );
    }

    if (signedIn) {
        return (
            <AuthLayout
                title="You're invited"
                description="Accepting your invitation…"
                brandText="Shoutrrr"
            >
                <AutoAccept invitation={invitation.data} />
            </AuthLayout>
        );
    }

    const data = invitation.data;

    return (
        <AuthLayout
            title="You're invited"
            description="Accept your invitation to join the workspace"
            brandText="Shoutrrr"
        >
            <div className="space-y-6">
                <div className="rounded-md border p-4">
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <span className="flex items-center gap-2 font-medium">
                            <Users className="size-4" />
                            {data.workspace_name}
                        </span>
                        <Badge variant="outline" className="capitalize">
                            {data.role}
                        </Badge>
                    </div>
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <User className="size-4" />
                        Invited by {data.inviter_name}
                    </p>
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Calendar className="size-4" />
                        Expires {new Date(data.expires_at).toLocaleDateString()}
                    </p>
                </div>

                <div className="space-y-3">
                    <Button
                        nativeButton={false}
                        className="w-full"
                        render={
                            <Link
                                to="/login"
                                search={{ invitation: token }}
                            />
                        }
                    >
                        {data.user_exists
                            ? 'Sign in & join'
                            : 'I already have an account'}
                    </Button>
                    {!data.user_exists && (
                        <Button
                            nativeButton={false}
                            variant="outline"
                            className="w-full"
                            render={
                                <Link
                                    to="/register"
                                    search={{ invitation: token }}
                                />
                            }
                        >
                            Create account & join
                        </Button>
                    )}
                </div>
            </div>
        </AuthLayout>
    );
}
