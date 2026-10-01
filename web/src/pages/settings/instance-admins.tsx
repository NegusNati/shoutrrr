import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import InputError from '@/components/common/input-error';
import RemoveInstanceOwnerDialog from '@/components/settings/remove-instance-owner-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Crown,
    MoreVertical,
    Search,
    Trash2,
    UserPlus,
} from '@/components/ui/icons';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useMeData } from '@/features/me/me';
import type { InstanceUser } from '@/features/settings/instance-settings';
import {
    instanceAdminsQuery,
    instanceSettingsKeys,
} from '@/features/settings/instance-settings';
import { apiFetch, ApiError, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { queryClient } from '@/lib/query-client';

export default function InstanceAdmins() {
    const currentUserId = useMeData()?.auth.user.id;
    const [submittedSearch, setSubmittedSearch] = useState('');
    const [query, setQuery] = useState('');
    const [ownerToRemove, setOwnerToRemove] = useState<InstanceUser | null>(
        null,
    );

    const { data } = useQuery(instanceAdminsQuery(submittedSearch));

    const invalidate = () =>
        queryClient.invalidateQueries({ queryKey: instanceSettingsKeys.admins });

    const removeOwner = useMutation({
        mutationFn: (ownerId: string) =>
            apiFetch(endpoints.settingsInstanceAdmin(ownerId), {
                method: 'DELETE',
            }),
        onSuccess: () => {
            void invalidate();
            toast.success('Instance owner removed');
            setOwnerToRemove(null);
        },
        onError: (err) => {
            toast.error(
                getErrorMessage(err, 'Could not remove the instance owner.'),
            );
            setOwnerToRemove(null);
        },
    });

    if (!data) {
        return null;
    }

    const owners = data.owners;
    const users = data.users;

    return (
        <div className="space-y-8">
            <Heading
                variant="small"
                title="Admins"
                description="Add registered users as instance owners. No invitation or acceptance is required."
            />

            <section className="space-y-4">
                <div className="space-y-1">
                    <h2 className="text-sm font-medium">Instance owners</h2>
                    <p className="text-sm text-muted-foreground">
                        These users can manage instance-wide settings.
                    </p>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>User</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead className="w-32 text-right">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {owners.map((owner) => (
                                <TableRow key={owner.id}>
                                    <TableCell>
                                        <UserSummary user={owner} />
                                    </TableCell>
                                    <TableCell>
                                        <Badge>
                                            <span className="flex items-center gap-1">
                                                <Crown className="size-3" />
                                                Owner
                                            </span>
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {owner.id !== currentUserId &&
                                            owners.length > 1 && (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        render={
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                            />
                                                        }
                                                    >
                                                        <MoreVertical className="size-4" />
                                                        <span className="sr-only">
                                                            Owner actions
                                                        </span>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onClick={() =>
                                                                setOwnerToRemove(
                                                                    owner,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                            Remove
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </section>

            <section className="space-y-4">
                <div className="space-y-1">
                    <h2 className="text-sm font-medium">Add owner</h2>
                    <p className="text-sm text-muted-foreground">
                        Search registered users by email, then grant the
                        instance owner role.
                    </p>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        setSubmittedSearch(query);
                    }}
                    className="flex gap-2"
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="search" className="sr-only">
                            Search users by email
                        </Label>
                        <Input
                            id="search"
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search by email"
                        />
                    </div>
                    <Button type="submit" variant="outline">
                        <Search className="size-4" />
                        Search
                    </Button>
                </form>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>User</TableHead>
                                <TableHead className="w-32 text-right">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={2}
                                        className="py-6 text-center text-sm text-muted-foreground"
                                    >
                                        {submittedSearch
                                            ? 'No registered users match this email search.'
                                            : 'Search by email to find a registered user.'}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                users.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell>
                                            <UserSummary user={user} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <AddOwnerButton email={user.email} />
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </section>

            <RemoveInstanceOwnerDialog
                owner={ownerToRemove}
                onClose={() => setOwnerToRemove(null)}
                onConfirm={() => {
                    if (ownerToRemove) {
                        removeOwner.mutate(ownerToRemove.id);
                    }
                }}
            />
        </div>
    );
}

function AddOwnerButton({ email }: { email: string }) {
    const [error, setError] = useState<string | null>(null);

    const add = useMutation({
        mutationFn: () =>
            apiFetch(endpoints.settingsInstanceAdmins, {
                method: 'POST',
                body: { email },
            }),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: instanceSettingsKeys.admins,
            });
            setError(null);
            toast.success('Instance owner added');
        },
        onError: (err) => {
            setError(
                err instanceof ApiError
                    ? (err.fieldError('email') ??
                          getErrorMessage(err, 'Could not add the owner.'))
                    : 'Could not add the owner.',
            );
        },
    });

    return (
        <div className="inline-flex flex-col items-end gap-1">
            <Button
                type="button"
                size="sm"
                disabled={add.isPending}
                onClick={() => add.mutate()}
            >
                <UserPlus className="size-4" />
                Add owner
            </Button>
            <InputError message={error ?? undefined} />
        </div>
    );
}

function UserSummary({ user }: { user: InstanceUser }) {
    return (
        <div className="flex items-center gap-3">
            <Avatar className="size-8">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <p className="truncate font-medium">{user.name}</p>
                <p className="truncate text-sm text-muted-foreground">
                    {user.email}
                </p>
            </div>
        </div>
    );
}
