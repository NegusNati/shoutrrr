import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import type { WorkspaceQuota } from '@/features/settings/instance-settings';
import { instanceSettingsKeys } from '@/features/settings/instance-settings';
import { apiFetch, ApiError, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { queryClient } from '@/lib/query-client';

type Props = {
    workspaceId: string;
    quota: WorkspaceQuota;
    // The initial workspace is always unlimited and the gate ignores any
    // override, so its quota can't be changed — show it locked.
    locked?: boolean;
};

const budgetInputId = 'workspace-x-budget';

export function WorkspaceQuotaEditor({ workspaceId, quota, locked }: Props) {
    const [unlimited, setUnlimited] = useState(quota.kind === 'unlimited');
    const [dollars, setDollars] = useState<number | ''>(quota.dollars ?? '');
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () =>
            apiFetch(endpoints.settingsInstanceWorkspaceBudget(workspaceId), {
                method: 'PUT',
                body: {
                    unlimited,
                    dollars: dollars === '' ? null : dollars,
                },
            }),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: instanceSettingsKeys.usage,
            });
            setError(null);
            toast.success('Workspace X budget updated');
        },
        onError: (err) => {
            setError(
                err instanceof ApiError
                    ? (err.fieldError('dollars') ??
                          getErrorMessage(err, 'Could not save the quota.'))
                    : 'Could not save the quota.',
            );
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

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                save.mutate();
            }}
            className="space-y-3"
        >
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
                        aria-invalid={error !== null}
                    />
                    {error && <p className="text-sm text-destructive">{error}</p>}
                </div>
            )}
            <Button type="submit" disabled={save.isPending}>
                {save.isPending ? 'Saving…' : 'Save quota'}
            </Button>
        </form>
    );
}
