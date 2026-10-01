import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import {
    updateWorkspaceBudget,
    type WorkspaceQuota,
} from '@/features/instance-settings/instance-settings';
import { ApiError, errorMessage } from '@/lib/api';

type Props = {
    workspaceId: string;
    quota: WorkspaceQuota;
    // The initial workspace is always unlimited and the gate ignores any override,
    // so its quota can't be changed — show it locked instead of a silent no-op.
    locked?: boolean;
    onSaved?: () => void;
};

const budgetInputId = 'workspace-x-budget';

export function WorkspaceQuotaEditor({
    workspaceId,
    quota,
    locked,
    onSaved,
}: Props) {
    const [unlimited, setUnlimited] = useState(quota.kind === 'unlimited');
    const [dollars, setDollars] = useState<number | ''>(quota.dollars ?? '');
    const [dollarsError, setDollarsError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () =>
            updateWorkspaceBudget(workspaceId, { unlimited, dollars }),
        onSuccess: () => {
            setDollarsError(null);
            toast.success('Workspace X budget updated');
            onSaved?.();
        },
        onError: (error) => {
            if (error instanceof ApiError && error.status === 422) {
                setDollarsError(
                    error.fieldError('dollars') ??
                        error.fieldError('unlimited') ??
                        null,
                );
            } else {
                toast.error(errorMessage(error, 'Could not update the budget'));
            }
        },
    });

    if (locked) {
        return (
            <div className="space-y-2">
                <h3 className="text-sm font-medium">X quota</h3>
                <p className="rounded-md border p-3 text-sm text-muted-foreground">
                    The initial workspace always has an unlimited X quota and
                    can’t be changed.
                </p>
            </div>
        );
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        save.mutate();
    }

    return (
        <form onSubmit={submit} className="space-y-3">
            <label className="flex items-center gap-2 text-sm">
                <Switch checked={unlimited} onCheckedChange={setUnlimited} />
                Unlimited X quota
            </label>
            {!unlimited && (
                <div className="space-y-1">
                    <label
                        htmlFor={budgetInputId}
                        className="text-xs text-muted-foreground"
                    >
                        Monthly X budget (USD)
                    </label>
                    <Input
                        id={budgetInputId}
                        type="number"
                        min={0}
                        step="0.01"
                        placeholder="Leave blank for instance default"
                        value={dollars}
                        onChange={(e) =>
                            setDollars(
                                e.target.value === ''
                                    ? ''
                                    : Number(e.target.value),
                            )
                        }
                        aria-invalid={Boolean(dollarsError)}
                    />
                    {dollarsError && (
                        <p className="text-sm text-destructive">
                            {dollarsError}
                        </p>
                    )}
                </div>
            )}
            <Button type="submit" disabled={save.isPending}>
                {save.isPending ? 'Saving…' : 'Save quota'}
            </Button>
        </form>
    );
}
