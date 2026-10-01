import { useQuery } from '@tanstack/react-query';

import Heading from '@/components/common/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { workspaceSubscriptionQuery } from '@/features/settings/workspace-settings';

const usd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 3,
});

function csrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

function formatMicrousd(value: number): string {
    return usd.format(value / 1_000_000);
}

// Guards against a raw PHP_INT_MAX (or anything past safe-integer precision)
// leaking through as "unlimited" instead of the expected `null`.
const UNLIMITED_THRESHOLD = Number.MAX_SAFE_INTEGER;

function isUnlimitedValue(value: number | null): boolean {
    return value === null || value >= UNLIMITED_THRESHOLD;
}

function formatXBudget(value: number | null): string {
    return isUnlimitedValue(value)
        ? 'Unlimited'
        : formatMicrousd(value as number);
}

// Renders the natural-language clause describing the monthly X budget, e.g.
// "an unlimited monthly X/Twitter usage budget" or "a $5.00/month X/Twitter
// usage budget" — avoids the awkward "a Unlimited/month" construction.
function xBudgetPhrase(value: number | null): string {
    return isUnlimitedValue(value)
        ? 'an unlimited monthly X/Twitter usage budget'
        : `a ${formatMicrousd(value as number)}/month X/Twitter usage budget`;
}

export default function WorkspaceSubscription() {
    const { data } = useQuery(workspaceSubscriptionQuery);

    if (!data) {
        return null;
    }

    const {
        subscribed,
        monthlyPrice,
        monthlyXBudgetMicrousd,
        monthlyXBudgetUsedMicrousd,
        monthlyXBudgetRemainingMicrousd,
        canManageSubscription,
        canAccessPortal,
    } = data;

    const monthlyUsagePercent =
        !isUnlimitedValue(monthlyXBudgetMicrousd) &&
        (monthlyXBudgetMicrousd as number) > 0
            ? Math.min(
                  100,
                  (monthlyXBudgetUsedMicrousd /
                      (monthlyXBudgetMicrousd as number)) *
                      100,
              )
            : 0;
    const remainingLabel = isUnlimitedValue(monthlyXBudgetRemainingMicrousd)
        ? 'Unlimited remaining'
        : `${formatXBudget(monthlyXBudgetRemainingMicrousd)} remaining`;

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Subscription"
                description="Manage publishing access for this workspace"
            />

            <Card>
                <CardHeader>
                    <CardTitle>Shoutrrr Cloud</CardTitle>
                    <CardDescription>
                        Unlimited seats with monthly X publishing included.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-2">
                        <div className="text-3xl font-semibold">
                            {usd.format(monthlyPrice / 100)}
                            <span className="text-base font-normal text-muted-foreground">
                                /month
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Includes unlimited seats, unlimited publishes to
                            every other platform, and{' '}
                            {xBudgetPhrase(monthlyXBudgetMicrousd)}.
                        </p>
                    </div>

                    <div className="rounded-2xl border bg-muted/30 p-4 text-sm">
                        <span className="font-medium">Status: </span>
                        {subscribed
                            ? 'Active subscription'
                            : 'Not subscribed'}
                    </div>

                    <div className="rounded-2xl border p-4">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="text-sm font-medium">
                                    X budget this month
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {remainingLabel}
                                </p>
                            </div>
                            <div className="text-right text-sm">
                                <span className="text-2xl font-semibold text-foreground">
                                    {formatMicrousd(monthlyXBudgetUsedMicrousd)}
                                </span>
                                <span className="text-muted-foreground">
                                    {' '}
                                    / {formatXBudget(monthlyXBudgetMicrousd)}
                                </span>
                            </div>
                        </div>
                        <div className="mt-4 h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-primary transition-all"
                                style={{ width: `${monthlyUsagePercent}%` }}
                            />
                        </div>
                    </div>

                    {canManageSubscription ? (
                        <form action="/billing/portal" method="post">
                            <input
                                type="hidden"
                                name="_token"
                                defaultValue={csrfToken()}
                            />
                            <Button type="submit">Manage subscription</Button>
                        </form>
                    ) : (
                        <div className="flex flex-wrap items-center gap-3">
                            <form action="/billing/checkout" method="post">
                                <input
                                    type="hidden"
                                    name="_token"
                                    defaultValue={csrfToken()}
                                />
                                <Button type="submit">
                                    Subscribe to publish
                                </Button>
                            </form>
                            {canAccessPortal && (
                                <form action="/billing/portal" method="post">
                                    <input
                                        type="hidden"
                                        name="_token"
                                        defaultValue={csrfToken()}
                                    />
                                    <Button type="submit" variant="outline">
                                        Billing portal
                                    </Button>
                                </form>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
