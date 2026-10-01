import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    instancePollingQuery,
    updatePollingSettings,
    type PollingGroup,
    type PollingSectionKey,
    type PollingSettings,
    type SectionPlatform,
} from '@/features/instance-settings/instance-settings';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError } from '@/lib/api';
import { errorMessage } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { PlatformName } from '@/types/compose';

export function pollingWithMinutes(
    settings: PollingSettings,
    group: PollingSectionKey,
    platform: PlatformName,
    value: string,
): PollingSettings {
    return {
        ...settings,
        [group]: {
            ...settings[group],
            [platform]: Number.parseInt(value || '0', 10),
        },
    };
}

export function pollingWithPlatformEnabled(
    settings: PollingSettings,
    group: PollingSectionKey,
    platform: PlatformName,
    enabled: boolean,
): PollingSettings {
    return {
        ...settings,
        [group]: {
            ...settings[group],
            enabled: {
                ...settings[group].enabled,
                [platform]: enabled,
            },
        },
    };
}

export default function InstancePollingPage() {
    useDocumentTitle('Instance polling');

    const queryClient = useQueryClient();
    const { data } = useQuery(instancePollingQuery);
    const [form, setForm] = useState<PollingSettings | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const settings = form ?? data?.settings;
    const sections = data?.sections;

    const save = useMutation({
        mutationFn: updatePollingSettings,
        onSuccess: (next) => {
            setForm(null);
            setErrors({});
            queryClient.setQueryData(['instance-settings', 'polling'], next);
            toast.success('Polling settings updated');
        },
        onError: (error) => {
            if (error instanceof ApiError && error.errors) {
                setErrors(
                    Object.fromEntries(
                        Object.entries(error.errors).map(([key, messages]) => [
                            key,
                            messages[0] ?? '',
                        ]),
                    ),
                );
            } else {
                toast.error(
                    errorMessage(error, 'Could not update polling settings'),
                );
            }
        },
    });

    function update(patch: Partial<PollingSettings>) {
        if (settings) {
            setForm({ ...settings, ...patch });
        }
    }

    function setMinutes(
        group: PollingSectionKey,
        platform: PlatformName,
        value: string,
    ) {
        if (settings) {
            setForm(pollingWithMinutes(settings, group, platform, value));
        }
    }

    // Per-platform enable toggles save immediately, matching the web page —
    // the rows are operational switches, not draft form fields.
    function setPlatformEnabled(
        group: PollingSectionKey,
        platform: PlatformName,
        enabled: boolean,
    ) {
        if (!settings) {
            return;
        }

        const next = pollingWithPlatformEnabled(
            settings,
            group,
            platform,
            enabled,
        );
        setForm(next);
        save.mutate(next);
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        if (settings) {
            save.mutate(settings);
        }
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Polling"
                description="Tune how often each platform checks for replies and post metrics"
            />

            <form onSubmit={handleSubmit} className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Feature availability</CardTitle>
                        <CardDescription>
                            Turn engagement, metrics, or messages off for the
                            whole instance without touching environment
                            variables. The sections below only take effect while
                            their switch here is on.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="engagement_enabled"
                                checked={settings?.engagement_enabled ?? false}
                                onCheckedChange={(checked) =>
                                    update({
                                        engagement_enabled: checked === true,
                                    })
                                }
                            />
                            <div className="space-y-1">
                                <Label htmlFor="engagement_enabled">
                                    Enable engagement
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    Governs the Engagement section below.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="metrics_enabled"
                                checked={settings?.metrics_enabled ?? false}
                                onCheckedChange={(checked) =>
                                    update({
                                        metrics_enabled: checked === true,
                                    })
                                }
                            />
                            <div className="space-y-1">
                                <Label htmlFor="metrics_enabled">
                                    Enable metrics
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    Governs both the Post metrics and Account
                                    metrics sections below.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="messages_enabled"
                                checked={settings?.messages_enabled ?? false}
                                onCheckedChange={(checked) =>
                                    update({
                                        messages_enabled: checked === true,
                                    })
                                }
                            />
                            <div className="space-y-1">
                                <Label htmlFor="messages_enabled">
                                    Enable messages
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    Shows the Messages inbox and polls direct
                                    messages on X, Bluesky, Instagram, and
                                    Facebook.
                                </p>
                            </div>
                        </div>

                        <div className="ml-7 flex items-start gap-3">
                            <Checkbox
                                id="direct_messages_enabled"
                                checked={
                                    settings?.direct_messages_enabled ?? false
                                }
                                disabled={!settings?.messages_enabled}
                                onCheckedChange={(checked) =>
                                    update({
                                        direct_messages_enabled:
                                            checked === true,
                                    })
                                }
                            />
                            <div className="space-y-1">
                                <Label
                                    htmlFor="direct_messages_enabled"
                                    className={cn(
                                        !settings?.messages_enabled &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    Request DM permissions when connecting
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    Asks for direct-message access during
                                    account connect. Existing accounts must
                                    reconnect before their DMs appear.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {settings && sections && (
                    <>
                        <PollingCard
                            title="Engagement"
                            description="The minimum time between reply checks. Fresh posts are checked more often and back off as they age, so this sets the floor, not a fixed interval."
                            group="engagement"
                            platforms={sections.engagement}
                            values={settings.engagement}
                            errors={errors}
                            onChange={setMinutes}
                            onEnabledChange={setPlatformEnabled}
                            minutesHelp="Minimum interval in minutes."
                            disabled={!settings.engagement_enabled}
                        />

                        <PollingCard
                            title="Post metrics"
                            description="The minimum time between metric refreshes. Fresh posts are refreshed more often and back off as they age, so this sets the floor, not a fixed interval."
                            group="post_metrics"
                            platforms={sections.post_metrics}
                            values={settings.post_metrics}
                            errors={errors}
                            onChange={setMinutes}
                            onEnabledChange={setPlatformEnabled}
                            minutesHelp="Minimum interval in minutes."
                            disabled={!settings.metrics_enabled}
                        />

                        <PollingCard
                            title="Account metrics"
                            description="How often to snapshot follower, following, and post counts for connected accounts."
                            group="account_metrics"
                            platforms={sections.account_metrics}
                            values={settings.account_metrics}
                            errors={errors}
                            onChange={setMinutes}
                            onEnabledChange={setPlatformEnabled}
                            disabled={!settings.metrics_enabled}
                        />
                    </>
                )}

                <Button type="submit" disabled={save.isPending}>
                    Save
                </Button>
            </form>
        </div>
    );
}

function PollingCard({
    title,
    description,
    group,
    platforms,
    values,
    errors,
    onChange,
    onEnabledChange,
    minutesHelp = 'Interval in minutes.',
    disabled = false,
}: {
    title: string;
    description: string;
    group: PollingSectionKey;
    platforms: SectionPlatform[];
    values: PollingGroup;
    errors: Record<string, string>;
    onChange: (
        group: PollingSectionKey,
        platform: PlatformName,
        value: string,
    ) => void;
    onEnabledChange: (
        group: PollingSectionKey,
        platform: PlatformName,
        enabled: boolean,
    ) => void;
    minutesHelp?: string;
    /** The instance-wide master switch for this section is off; every row below is moot. */
    disabled?: boolean;
}) {
    const hasDisabledPlatform = platforms.some(
        (p) => !values.enabled[p.platform],
    );

    return (
        <Card className={cn(disabled && 'opacity-60')}>
            <CardHeader>
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <CardTitle>{title}</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </div>
                    {disabled ? (
                        <span className="rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                            Disabled instance-wide
                        </span>
                    ) : (
                        hasDisabledPlatform && (
                            <span className="rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                                Partially disabled
                            </span>
                        )
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {platforms.map((p) => {
                    const errorKey = `${group}.${p.platform}`;
                    const isEnabled = values.enabled[p.platform];

                    return (
                        <div
                            key={p.platform}
                            className="grid gap-2 sm:grid-cols-[1fr_9rem] sm:items-start"
                        >
                            <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={`${group}-${p.platform}-enabled`}
                                        checked={isEnabled}
                                        disabled={disabled}
                                        onCheckedChange={(checked) =>
                                            onEnabledChange(
                                                group,
                                                p.platform,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`${group}-${p.platform}-enabled`}
                                    >
                                        {p.label}
                                    </Label>
                                    {!disabled && !isEnabled && (
                                        <span className="rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                                            Temporarily disabled
                                        </span>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {isEnabled
                                        ? minutesHelp
                                        : `Polling for ${p.label} is paused.`}
                                </p>
                            </div>
                            <div>
                                <Input
                                    id={`${group}-${p.platform}`}
                                    type="number"
                                    min={5}
                                    max={10080}
                                    step={5}
                                    value={values[p.platform]}
                                    disabled={disabled || !isEnabled}
                                    onChange={(event) =>
                                        onChange(
                                            group,
                                            p.platform,
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={errors[errorKey]} />
                            </div>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
