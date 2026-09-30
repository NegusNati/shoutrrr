import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import {
    instanceSettingsKeys,
    updateWorkspaceXBudget,
    type InstanceUsageDrilldown,
    type WorkspaceQuota,
} from '@/features/settings/instance-settings';
import { ApiError, getErrorMessage } from '@/lib/api';

type Props = {
    workspaceId: string;
    quota: WorkspaceQuota;
    // The initial workspace is always unlimited and the gate ignores any override,
    // so its quota can't be changed — show it locked instead of a silent no-op.
    locked?: boolean;
};

const budgetInputId = 'workspace-x-budget';

export function WorkspaceQuotaEditor({ workspaceId, quota, locked }: Props) {
    const queryClient = useQueryClient();
    const [unlimited, setUnlimited] = useState(quota.kind === 'unlimited');
    const [dollars, setDollars] = useState<number | ''>(quota.dollars ?? '');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

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

    async function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setProcessing(true);
        setError(null);

        try {
            const response = await updateWorkspaceXBudget(workspaceId, {
                unlimited,
                dollars: unlimited || dollars === '' ? null : dollars,
            });
            queryClient.setQueryData<InstanceUsageDrilldown>(
                instanceSettingsKeys.drilldown(workspaceId),
                (old) =>
                    old && {
                        ...old,
                        workspace: {
                            ...old.workspace,
                            quota: response.quota,
                        },
                    },
            );
            // The row's QuotaBadge lives in the list query — refresh it too.
            await queryClient.invalidateQueries({
                queryKey: ['settings', 'instance', 'usage'],
            });
            toast.success('Workspace X budget updated');
        } catch (err) {
            if (err instanceof ApiError) {
                setError(
                    err.fieldError('dollars') ??
                        err.fieldError('unlimited') ??
                        err.message,
                );
            } else {
                setError(getErrorMessage(err, 'Could not update the quota.'));
            }
        } finally {
            setProcessing(false);
        }
    }

    return (
        <form onSubmit={submit} className="space-y-3">
            <label className="flex items-center gap-2 text-sm">
                <Switch
                    checked={unlimited}
                    onCheckedChange={setUnlimited}
                />
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
                        aria-invalid={Boolean(error)}
                    />
                    {error && (
                        <p className="text-sm text-destructive">{error}</p>
                    )}
                </div>
            )}
            <Button type="submit" disabled={processing}>
                {processing ? 'Saving…' : 'Save quota'}
            </Button>
        </form>
    );
}
