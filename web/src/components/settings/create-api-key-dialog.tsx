import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';

import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Calendar as CalendarIcon, Plus } from '@/components/ui/icons';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CreateApiKeyResponse } from '@/features/settings/workspace-settings';
import { workspaceSettingsKeys } from '@/features/settings/workspace-settings';
import { apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { dayjs } from '@/lib/datetime/dayjs';
import { fieldString } from '@/lib/forms';
import { queryClient } from '@/lib/query-client';

const SCOPE_ITEMS = [
    { value: 'read', label: 'Read' },
    { value: 'write', label: 'Read & write' },
];

export default function CreateApiKeyDialog({
    onCreated,
}: {
    onCreated: (plainTextApiKey: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [expiresAt, setExpiresAt] = useState<Date | undefined>(undefined);
    const [dateOpen, setDateOpen] = useState(false);

    function reset() {
        setExpiresAt(undefined);
        setDateOpen(false);
        setError(null);
    }

    const create = useMutation({
        mutationFn: (body: {
            name: string;
            scope: string;
            expires_at?: string;
        }) =>
            apiFetch<CreateApiKeyResponse>(endpoints.settingsWorkspaceApiKeys, {
                method: 'POST',
                body,
            }),
        onSuccess: (data) => {
            void queryClient.invalidateQueries({
                queryKey: workspaceSettingsKeys.apiKeys,
            });
            toast.success('API key created');
            onCreated(data.plainTextApiKey);
            setOpen(false);
            reset();
        },
        onError: (err) => {
            setError(getErrorMessage(err, 'Could not create the API key.'));
        },
    });

    const onSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const data = new FormData(e.currentTarget);
        const expires = fieldString(data, 'expires_at');
        create.mutate({
            name: fieldString(data, 'name'),
            scope: fieldString(data, 'scope'),
            ...(expires ? { expires_at: expires } : {}),
        });
    };

    // Expiry must be a future date — the server rejects anything not after
    // now, so disable today and earlier in the picker.
    const earliest = dayjs().add(1, 'day').startOf('day').toDate();

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    reset();
                }
            }}
        >
            <DialogTrigger render={<Button size="sm" />}>
                <Plus className="size-4" />
                Create key
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create an API key</DialogTitle>
                    <DialogDescription>
                        The key acts on this workspace only. You&apos;ll see the
                        full key once, right after it&apos;s created.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit}>
                    <div className="space-y-4 py-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                placeholder="CI deploy bot"
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="scope">Access</Label>
                            <Select
                                name="scope"
                                defaultValue="read"
                                items={SCOPE_ITEMS}
                            >
                                <SelectTrigger id="scope">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {SCOPE_ITEMS.map((item) => (
                                        <SelectItem
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Read keys can fetch data; read &amp; write keys
                                can also create and change it.
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label>
                                Expiry{' '}
                                <span className="text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            {expiresAt && (
                                <input
                                    type="hidden"
                                    name="expires_at"
                                    value={dayjs(expiresAt).format(
                                        'YYYY-MM-DD',
                                    )}
                                />
                            )}
                            <Popover open={dateOpen} onOpenChange={setDateOpen}>
                                <PopoverTrigger
                                    render={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full justify-start font-normal"
                                        />
                                    }
                                >
                                    <CalendarIcon className="size-4 text-muted-foreground" />
                                    {expiresAt ? (
                                        dayjs(expiresAt).format('MMM D, YYYY')
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Never expires
                                        </span>
                                    )}
                                </PopoverTrigger>
                                <PopoverContent
                                    align="start"
                                    className="w-auto p-0"
                                >
                                    <Calendar
                                        mode="single"
                                        autoFocus
                                        selected={expiresAt}
                                        onSelect={(date) => {
                                            setExpiresAt(date);
                                            setDateOpen(false);
                                        }}
                                        disabled={{ before: earliest }}
                                    />
                                    {expiresAt && (
                                        <div className="border-t p-2">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="w-full"
                                                onClick={() => {
                                                    setExpiresAt(undefined);
                                                    setDateOpen(false);
                                                }}
                                            >
                                                Clear
                                            </Button>
                                        </div>
                                    )}
                                </PopoverContent>
                            </Popover>
                            <p className="text-xs text-muted-foreground">
                                Leave empty for a key that never expires.
                            </p>
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
                        <Button type="submit" disabled={create.isPending}>
                            {create.isPending ? 'Creating…' : 'Create key'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
