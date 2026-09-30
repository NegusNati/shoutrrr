import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    instanceSettingsKeys,
    instanceSettingsQuery,
    updateInstanceSettings,
    type InstanceGeneralSettings,
} from '@/features/settings/instance-settings';
import { getErrorMessage } from '@/lib/api';

export default function InstanceGeneral() {
    const { data } = useQuery(instanceSettingsQuery);
    const queryClient = useQueryClient();

    const [form, setForm] = useState<InstanceGeneralSettings | null>(null);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        document.title = 'Instance settings';
    }, []);

    if (!data) {
        return null;
    }

    const values: InstanceGeneralSettings = form ?? {
        ...data.settings,
        workspace_creation_enabled:
            data.workspaces_enabled && data.settings.workspace_creation_enabled,
    };

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!data || processing) {
            return;
        }

        setProcessing(true);
        try {
            const response = await updateInstanceSettings(values);
            queryClient.setQueryData(instanceSettingsKeys.general, response);
            setForm(null);
            toast.success('Instance settings updated');
        } catch (error) {
            toast.error(
                getErrorMessage(error, 'Could not update instance settings.'),
            );
        } finally {
            setProcessing(false);
        }
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="General"
                description="Control signups and workspace creation for this instance"
            />

            <form onSubmit={handleSubmit} className="space-y-6">
                <div className="space-y-4">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="registrations_enabled"
                            checked={values.registrations_enabled}
                            onCheckedChange={(checked) =>
                                setForm({
                                    ...values,
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
                            checked={values.workspace_creation_enabled}
                            disabled={!data.workspaces_enabled}
                            onCheckedChange={(checked) =>
                                setForm({
                                    ...values,
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
                                {data.workspaces_enabled
                                    ? 'When disabled, users can still access workspaces they already belong to.'
                                    : 'Workspace creation is unavailable because workspaces are disabled by the WORKSPACES_ENABLED environment setting.'}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="usage_tracking_enabled"
                            checked={values.usage_tracking_enabled}
                            onCheckedChange={(checked) =>
                                setForm({
                                    ...values,
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
                            checked={values.quote_tweets_enabled}
                            onCheckedChange={(checked) =>
                                setForm({
                                    ...values,
                                    quote_tweets_enabled: checked === true,
                                })
                            }
                        />
                        <div className="space-y-1">
                            <Label htmlFor="quote_tweets_enabled">
                                Quote tweets on X from pasted links
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                When enabled, a post link in an X update becomes
                                a quote tweet instead of a plain link. Requires
                                X Enterprise API access; leave off on other
                                tiers. Off by default.
                            </p>
                        </div>
                    </div>
                </div>

                <Button type="submit" disabled={processing}>
                    Save
                </Button>
            </form>
        </div>
    );
}
