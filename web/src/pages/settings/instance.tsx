import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { InstanceGeneralSettings } from '@/features/settings/instance-settings';
import { instanceOverviewQuery } from '@/features/settings/instance-settings';
import { apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';

export default function InstanceGeneral() {
    const { data } = useQuery(instanceOverviewQuery);
    const [form, setForm] = useState<InstanceGeneralSettings | null>(null);

    useEffect(() => {
        if (data && form === null) {
            setForm({
                registrations_enabled: data.settings.registrations_enabled,
                workspace_creation_enabled:
                    data.workspaces_enabled &&
                    data.settings.workspace_creation_enabled,
                usage_tracking_enabled: data.settings.usage_tracking_enabled,
                quote_tweets_enabled: data.settings.quote_tweets_enabled,
            });
        }
    }, [data, form]);

    const save = useMutation({
        mutationFn: (body: InstanceGeneralSettings) =>
            apiFetch(endpoints.settingsInstance, {
                method: 'PUT',
                body,
            }),
        onSuccess: () => {
            toast.success('Instance settings updated');
        },
        onError: (err) => {
            toast.error(
                getErrorMessage(err, 'Could not update instance settings.'),
            );
        },
    });

    if (!data || !form) {
        return null;
    }

    const workspacesEnabled = data.workspaces_enabled;

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="General"
                description="Control signups and workspace creation for this instance"
            />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate(form);
                }}
                className="space-y-6"
            >
                <div className="space-y-4">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="registrations_enabled"
                            checked={form.registrations_enabled}
                            onCheckedChange={(checked) =>
                                setForm({
                                    ...form,
                                    registrations_enabled: checked === true,
                                })
                            }
                        />
                        <div className="space-y-1">
                            <Label htmlFor="registrations_enabled">
                                Allow public registration
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                When disabled, new users can only join with a
                                workspace invitation.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="workspace_creation_enabled"
                            checked={form.workspace_creation_enabled}
                            disabled={!workspacesEnabled}
                            onCheckedChange={(checked) =>
                                setForm({
                                    ...form,
                                    workspace_creation_enabled:
                                        checked === true,
                                })
                            }
                        />
                        <div className="space-y-1">
                            <Label htmlFor="workspace_creation_enabled">
                                Allow users to create workspaces
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                {workspacesEnabled
                                    ? 'When disabled, users can still access workspaces they already belong to.'
                                    : 'Workspace creation is unavailable because workspaces are disabled by the WORKSPACES_ENABLED environment setting.'}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="usage_tracking_enabled"
                            checked={form.usage_tracking_enabled}
                            onCheckedChange={(checked) =>
                                setForm({
                                    ...form,
                                    usage_tracking_enabled: checked === true,
                                })
                            }
                        />
                        <div className="space-y-1">
                            <Label htmlFor="usage_tracking_enabled">
                                Track platform API usage
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                When enabled, records per-workspace API usage
                                (posts, reads, requests) for cost and abuse
                                monitoring. Off by default.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="quote_tweets_enabled"
                            checked={form.quote_tweets_enabled}
                            onCheckedChange={(checked) =>
                                setForm({
                                    ...form,
                                    quote_tweets_enabled: checked === true,
                                })
                            }
                        />
                        <div className="space-y-1">
                            <Label htmlFor="quote_tweets_enabled">
                                Quote tweets on X from pasted links
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                When enabled, a post link in an X update
                                becomes a quote tweet instead of a plain link.
                                Requires X Enterprise API access; leave off on
                                other tiers. Off by default.
                            </p>
                        </div>
                    </div>
                </div>

                <Button type="submit" disabled={save.isPending}>
                    Save
                </Button>
            </form>
        </div>
    );
}
