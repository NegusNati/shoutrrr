import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';

import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Loader2 } from '@/components/ui/icons';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { api, ApiError } from '@/lib/api';
import { queryClient } from '@/lib/query-client';

export function CreateWorkspaceDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [name, setName] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const create = useMutation({
        mutationFn: () => api.post('workspaces', { name }),
        onSuccess: () => {
            void queryClient.invalidateQueries();
            setName('');
            setErrors({});
            onOpenChange(false);
        },
        onError: (error) => {
            if (error instanceof ApiError && error.status === 422) {
                setErrors(error.errors);
            }
        },
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create workspace</DialogTitle>
                    <DialogDescription>
                        Workspaces keep your team and data separate.
                    </DialogDescription>
                </DialogHeader>

                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setErrors({});
                        create.mutate();
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="workspace-name">Name</Label>
                        <Input
                            id="workspace-name"
                            name="name"
                            placeholder="Acme Inc."
                            autoFocus
                            required
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                        />
                        <InputError message={errors.name?.[0]} />
                    </div>

                    <Button type="submit" disabled={create.isPending}>
                        {create.isPending ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            'Create workspace'
                        )}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
