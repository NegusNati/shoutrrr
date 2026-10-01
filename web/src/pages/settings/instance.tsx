import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    instanceSettingsQuery,
    updateInstanceSettings,
    type InstanceSettings,
} from '@/features/instance-settings/instance-settings';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { errorMessage } from '@/lib/api';

export default function InstanceSettingsPage() {
    useDocumentTitle('Instance settings');

    const { data } = useQuery(instanceSettingsQuery);
    const [form, setForm] = useState<InstanceSettings | null>(null);

    const settings = form ?? {
        registrations_enabled: data?.settings.registrations_enabled ?? false,
        workspace_creation_enabled:
            data !== undefined &&
            data.workspaces_enabled &&
            data.settings.workspace_creation_enabled,
        usage_tracking_enabled: data?.settings.usage_tracking_enabled ?? false,
        quote_tweets_enabled: data?.settings.quote_tweets_enabled ?? false,
    };
    const workspacesEnabled = data?.workspaces_enabled ?? false;

    const save = useMutation({
        mutationFn: updateInstanceSettings,
        onSuccess: () => {
            setForm(null);
            toast.success('Instance settings updated');
        },
        onError: (error) =>
            toast.error(errorMessage(error, 'Could not save settings')),
    });

    function update(key: keyof InstanceSettings, value: boolean) {
        setForm({ ...settings, [key]: value });
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        save.mutate(settings);
    }

    return (
        <div className="space-y-6">
            <h1 className="sr-only">Instance settings</h1>

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
                            checked={settings.registrations_enabled === true}
                            onCheckedChange={(checked) =>
                                update(
                                    'registrations_enabled',
                                    checked === true,
                                )
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
                            checked={
                                settings.workspace_creation_enabled === true
                            }
                            disabled={!workspacesEnabled}
                            onCheckedChange={(checked) =>
                                update(
                                    'workspace_creation_enabled',
                                    checked === true,
                                )
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
                            checked={settings.usage_tracking_enabled === true}
                            onCheckedChange={(checked) =>
                                update(
                                    'usage_tracking_enabled',
                                    checked === true,
                                )
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
                            checked={settings.quote_tweets_enabled === true}
                            onCheckedChange={(checked) =>
                                update('quote_tweets_enabled', checked === true)
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

                <Button type="submit" disabled={save.isPending}>
                    Save
                </Button>
            </form>
        </div>
    );
}
