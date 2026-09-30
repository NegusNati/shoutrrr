import { useQuery } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';

import Heading from '@/components/common/heading';
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
import {
    instanceAdminsQuery,
    useAddInstanceOwner,
    useRemoveInstanceOwner,
    type InstanceUser,
} from '@/features/settings/instance-settings';

export default function InstanceAdmins() {
    const me = useMeData();

    const [search, setSearch] = useState('');
    const [submitted, setSubmitted] = useState('');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const { data } = useQuery(instanceAdminsQuery(submitted));
    const addOwner = useAddInstanceOwner();
    const removeOwner = useRemoveInstanceOwner();

    const [ownerToRemove, setOwnerToRemove] = useState<InstanceUser | null>(
        null,
    );

    useEffect(() => {
        document.title = 'Instance admins';
    }, []);

    function confirmRemoveOwner() {
        if (!ownerToRemove) {
            return;
        }

        removeOwner.mutate(ownerToRemove.id, {
            onSettled: () => setOwnerToRemove(null),
        });
    }

    function handleSearch(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSubmitted(search.trim());
    }

    function handleSearchChange(value: string) {
        setSearch(value);
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }
        debounceRef.current = setTimeout(() => {
            setSubmitted(value.trim());
        }, 250);
    }

    const owners = data?.owners ?? [];
    const users = data?.users ?? [];

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
                                        {owner.id !== me?.auth.user.id &&
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

                <form onSubmit={handleSearch} className="flex gap-2">
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="search" className="sr-only">
                            Search users by email
                        </Label>
                        <Input
                            id="search"
                            type="search"
                            value={search}
                            onChange={(event) =>
                                handleSearchChange(event.target.value)
                            }
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
                                        {submitted
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
                                            <Button
                                                type="button"
                                                size="sm"
                                                disabled={
                                                    addOwner.isPending
                                                }
                                                onClick={() =>
                                                    addOwner.mutate(
                                                        user.email,
                                                    )
                                                }
                                            >
                                                <UserPlus className="size-4" />
                                                Add owner
                                            </Button>
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
                onConfirm={confirmRemoveOwner}
            />
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
