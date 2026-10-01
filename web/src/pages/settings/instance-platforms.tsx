import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    instancePlatformsQuery,
    instanceSettingsKeys,
} from '@/features/settings/instance-settings';
import { apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { queryClient } from '@/lib/query-client';
import type { PlatformName } from '@/types/compose';

export default function InstancePlatforms() {
    const { data } = useQuery(instancePlatformsQuery);

    const save = useMutation({
        mutationFn: (body: {
            platforms: Record<string, boolean>;
            linkedin_community_management_enabled: boolean;
        }) =>
            apiFetch(endpoints.settingsInstancePlatforms, {
                method: 'PUT',
                body,
            }),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: instanceSettingsKeys.platforms,
            });
        },
        onError: (err) => {
            toast.error(
                getErrorMessage(err, 'Could not update platform settings.'),
            );
        },
    });

    if (!data) {
        return null;
    }

    const platforms = data.platforms;
    const linkedinEnabled = data.linkedin_community_management_enabled;

    function applySave(overrides: {
        platforms?: Record<string, boolean>;
        linkedin_community_management_enabled?: boolean;
    }) {
        save.mutate({
            platforms: Object.fromEntries(
                platforms.map((p) => [p.platform, p.enabled]),
            ),
            linkedin_community_management_enabled: linkedinEnabled,
            ...overrides,
        });
    }

    function setEnabled(platform: PlatformName, enabled: boolean) {
        applySave({
            platforms: Object.fromEntries(
                platforms.map((p) => [
                    p.platform,
                    p.platform === platform ? enabled : p.enabled,
                ]),
            ),
        });
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Platforms"
                description="Turn a social platform on or off for everyone on this instance. Disabling a platform hides it from the composer, blocks new connections, and skips any scheduled posts to it."
            />

            <Card>
                <CardHeader>
                    <CardTitle>Available platforms</CardTitle>
                    <CardDescription>
                        A disabled platform is frozen instance-wide.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {platforms.map((platform) => (
                        <div
                            key={platform.platform}
                            className="flex items-center gap-2"
                        >
                            <Checkbox
                                id={`platform-${platform.platform}`}
                                checked={platform.enabled}
                                onCheckedChange={(checked) =>
                                    setEnabled(
                                        platform.platform,
                                        checked === true,
                                    )
                                }
                            />
                            <Label htmlFor={`platform-${platform.platform}`}>
                                {platform.label}
                            </Label>
                            {!platform.configured && (
                                <span className="rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                                    Not configured
                                </span>
                            )}
                        </div>
                    ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>LinkedIn</CardTitle>
                    <CardDescription>
                        Features that need LinkedIn&apos;s restricted Community
                        Management API.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="linkedin_community_management_enabled"
                            checked={linkedinEnabled}
                            onCheckedChange={(checked) =>
                                applySave({
                                    linkedin_community_management_enabled:
                                        checked === true,
                                })
                            }
                        />
                        <div className="space-y-1">
                            <Label htmlFor="linkedin_community_management_enabled">
                                LinkedIn Pages &amp; engagement inbox (Community
                                Management API)
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                When enabled, connecting LinkedIn requests the
                                restricted Community Management scopes so
                                members can connect the LinkedIn Pages they
                                administer and the engagement inbox can read
                                replies. Requires your LinkedIn app to be
                                approved for the Community Management API —
                                leave off otherwise, or LinkedIn will reject the
                                connection. Reconnect existing LinkedIn accounts
                                after enabling. Off by default.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

