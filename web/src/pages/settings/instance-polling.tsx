import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
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
import type {
    PollingGroup,
    PollingSectionKey,
    PollingSettings,
    SectionPlatform,
} from '@/features/settings/instance-settings';
import {
    instancePollingQuery,
    instanceSettingsKeys,
} from '@/features/settings/instance-settings';
import { apiFetch, ApiError, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { queryClient } from '@/lib/query-client';
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

export default function InstancePolling() {
    const { data } = useQuery(instancePollingQuery);
    const [form, setForm] = useState<PollingSettings | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (data && form === null) {
            setForm(data.settings);
        }
    }, [data, form]);

    const save = useMutation({
        mutationFn: (body: PollingSettings) =>
            apiFetch(endpoints.settingsInstancePolling, {
                method: 'PUT',
                body,
            }),
        onSuccess: (_result, savedSettings) => {
            void queryClient.invalidateQueries({
                queryKey: instanceSettingsKeys.polling,
            });
            setFieldErrors({});
            setForm(savedSettings);
            toast.success('Polling settings updated');
        },
        onError: (err) => {
            if (err instanceof ApiError && err.status === 422) {
                setFieldErrors(
                    Object.fromEntries(
                        Object.entries(err.errors).map(([key, messages]) => [
                            key,
                            messages[0] ?? '',
                        ]),
                    ),
                );
            }
            toast.error(
                getErrorMessage(err, 'Could not update polling settings.'),
            );
        },
    });

    if (!data || !form) {
        return null;
    }

    const sections = data.sections;
    const current = form;

    function setMinutes(
        group: PollingSectionKey,
        platform: PlatformName,
        value: string,
    ) {
        setForm(pollingWithMinutes(current, group, platform, value));
    }

    function setPlatformEnabled(
        group: PollingSectionKey,
        platform: PlatformName,
        enabled: boolean,
    ) {
        const next = pollingWithPlatformEnabled(
            current,
            group,
            platform,
            enabled,
        );
        setForm(next);
        save.mutate(next);
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Polling"
                description="Tune how often each platform checks for replies and post metrics"
            />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate(current);
                }}
                className="space-y-6"
            >
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
                                checked={current.engagement_enabled}
                                onCheckedChange={(checked) =>
                                    setForm({
                                        ...current,
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
                                checked={current.metrics_enabled}
                                onCheckedChange={(checked) =>
                                    setForm({
                                        ...current,
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
                                checked={current.messages_enabled}
                                onCheckedChange={(checked) =>
                                    setForm({
                                        ...current,
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
                                checked={current.direct_messages_enabled}
                                disabled={!current.messages_enabled}
                                onCheckedChange={(checked) =>
                                    setForm({
                                        ...current,
                                        direct_messages_enabled:
                                            checked === true,
                                    })
                                }
                            />
                            <div className="space-y-1">
                                <Label
                                    htmlFor="direct_messages_enabled"
                                    className={cn(
                                        !current.messages_enabled &&
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

                <PollingCard
                    title="Engagement"
                    description="The minimum time between reply checks. Fresh posts are checked more often and back off as they age, so this sets the floor, not a fixed interval."
                    group="engagement"
                    platforms={sections.engagement}
                    values={current.engagement}
                    errors={fieldErrors}
                    onChange={setMinutes}
                    onEnabledChange={setPlatformEnabled}
                    minutesHelp="Minimum interval in minutes."
                    disabled={!current.engagement_enabled}
                />

                <PollingCard
                    title="Post metrics"
                    description="The minimum time between metric refreshes. Fresh posts are refreshed more often and back off as they age, so this sets the floor, not a fixed interval."
                    group="post_metrics"
                    platforms={sections.post_metrics}
                    values={current.post_metrics}
                    errors={fieldErrors}
                    onChange={setMinutes}
                    onEnabledChange={setPlatformEnabled}
                    minutesHelp="Minimum interval in minutes."
                    disabled={!current.metrics_enabled}
                />

                <PollingCard
                    title="Account metrics"
                    description="How often to snapshot follower, following, and post counts for connected accounts."
                    group="account_metrics"
                    platforms={sections.account_metrics}
                    values={current.account_metrics}
                    errors={fieldErrors}
                    onChange={setMinutes}
                    onEnabledChange={setPlatformEnabled}
                    disabled={!current.metrics_enabled}
                />

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
    errors: Partial<Record<string, string>>;
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
