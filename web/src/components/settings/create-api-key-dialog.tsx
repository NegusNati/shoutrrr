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
import type { CreatedApiKey } from '@/features/settings/workspace-settings';
import { ApiError, apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { dayjs } from '@/lib/datetime/dayjs';
import { fieldString } from '@/lib/forms';

const SCOPE_ITEMS = [
    { value: 'read', label: 'Read' },
    { value: 'write', label: 'Read & write' },
];

export default function CreateApiKeyDialog({
    onCreated,
}: {
    /** Receives the one-time plaintext key so the page can reveal it. */
    onCreated: (created: CreatedApiKey) => void;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [expiresAt, setExpiresAt] = useState<Date | undefined>(undefined);
    const [dateOpen, setDateOpen] = useState(false);
    const formId = 'create-api-key-form';

    function reset() {
        setExpiresAt(undefined);
        setDateOpen(false);
        setErrors({});
    }

    // Expiry must be a future date — the server rejects anything not after now,
    // so disable today and earlier in the picker.
    const earliest = dayjs().add(1, 'day').startOf('day').toDate();

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);

        setProcessing(true);
        setErrors({});

        try {
            const created = await apiFetch<CreatedApiKey>(
                endpoints.settingsWorkspaceApiKeys,
                {
                    method: 'POST',
                    body: {
                        name: fieldString(data, 'name'),
                        scope: fieldString(data, 'scope') || 'read',
                        ...(expiresAt
                            ? {
                                  expires_at:
                                      dayjs(expiresAt).format('YYYY-MM-DD'),
                              }
                            : {}),
                    },
                },
            );
            toast.success('API key created');
            setOpen(false);
            reset();
            onCreated(created);
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                setErrors(
                    Object.fromEntries(
                        Object.entries(error.errors).map(([k, v]) => [k, v[0]]),
                    ),
                );
            } else {
                toast.error(
                    getErrorMessage(error, 'Could not create the API key.'),
                );
            }
        } finally {
            setProcessing(false);
        }
    }

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
                <form id={formId} onSubmit={handleSubmit}>
                    <div className="space-y-4 py-2">
                        <div className="grid gap-2">
                            <Label htmlFor="api-key-name">Name</Label>
                            <Input
                                id="api-key-name"
                                name="name"
                                placeholder="CI deploy bot"
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="api-key-scope">Access</Label>
                            <Select
                                name="scope"
                                defaultValue="read"
                                items={SCOPE_ITEMS}
                            >
                                <SelectTrigger id="api-key-scope">
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
                            <InputError message={errors.scope} />
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
                            <InputError message={errors.expires_at} />
                            <p className="text-xs text-muted-foreground">
                                Leave empty for a key that never expires.
                            </p>
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
                        {processing ? 'Creating…' : 'Create key'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
