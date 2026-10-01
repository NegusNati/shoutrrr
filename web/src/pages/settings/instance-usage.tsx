import { useMutation, useQuery } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useEffect, useRef, useState } from 'react';

import Heading from '@/components/common/heading';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    DrilldownOwner,
    PaginationLink,
    UsageFilters,
    WorkspaceQuota,
    XUsageData,
    XUsageResponse,
} from '@/features/settings/instance-settings';
import { instanceUsageQuery } from '@/features/settings/instance-settings';
import { apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { cn } from '@/lib/utils';

import { WorkspaceQuotaEditor } from './instance-usage/workspace-quota-editor';

const sortItems: { value: UsageFilters['sort']; label: string }[] = [
    { value: 'spend', label: 'Highest spend' },
    { value: 'name', label: 'Name (A–Z)' },
];

export function xUsageTotal(data: XUsageData | null) {
    if (!data) {
        return 0;
    }

    if (typeof data.project_usage === 'number') {
        return data.project_usage;
    }

    const dailyUsage = Array.isArray(data.daily_project_usage)
        ? data.daily_project_usage
        : [];

    return dailyUsage.reduce(
        (total, day) =>
            total +
            (day.usage ?? []).reduce(
                (dayTotal, app) => dayTotal + (app.tweets_consumed ?? 0),
                0,
            ),
        0,
    );
}

export function canFetchXUsage(isConfigured: boolean, isProcessing: boolean) {
    return isConfigured && !isProcessing;
}

export default function InstanceUsage({ filters }: { filters: UsageFilters }) {
    const navigate = useNavigate();
    const { data } = useQuery(instanceUsageQuery(filters));

    const [xUsage, setXUsage] = useState<XUsageResponse | null>(null);
    const [xUsageError, setXUsageError] = useState<string | null>(null);
    const [localSearch, setLocalSearch] = useState(filters.search ?? '');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const fetchXUsage = useMutation({
        mutationFn: () =>
            apiFetch<XUsageResponse>(endpoints.settingsInstanceUsageX),
        onSuccess: (response) => {
            setXUsage(response);
            setXUsageError(null);
        },
        onError: (err) => {
            setXUsageError(
                getErrorMessage(err, 'Unable to fetch X API usage.'),
            );
        },
    });

    // Keep the search box in sync when filters change from outside.
    useEffect(() => {
        setLocalSearch(filters.search ?? '');
    }, [filters.search]);

    function setFilters(next: Partial<UsageFilters>) {
        void navigate({
            to: '/settings/instance/usage',
            search: (prev) => ({
                search:
                    typeof prev.search === 'string' ? prev.search : null,
                sort: prev.sort === 'name' ? 'name' : 'spend',
                workspace:
                    typeof prev.workspace === 'string'
                        ? prev.workspace
                        : null,
                page:
                    typeof prev.page === 'number' && prev.page > 0
                        ? Math.floor(prev.page)
                        : 1,
                ...next,
            }),
            replace: true,
        });
    }

    function handleSearchChange(value: string) {
        setLocalSearch(value);
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }
        debounceRef.current = setTimeout(() => {
            setFilters({ search: value || null, page: 1 });
        }, 250);
    }

    if (!data) {
        return null;
    }

    const workspaceUsage = data.workspace_usage;
    const pricingCurrency = data.pricing_currency;
    const drilldown = data.drilldown;

    return (
        <div className="space-y-8">
            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Usage"
                    description="Review tracked platform API usage by workspace. Estimates use mapped X API pricing where available."
                />
                <p className="text-xs text-muted-foreground">
                    Pricing estimates are informational and based on the{' '}
                    <a
                        href={data.pricing_source}
                        target="_blank"
                        rel="noreferrer"
                        className="underline underline-offset-4"
                    >
                        X API pricing page
                    </a>
                    . Non-X platforms and unmapped operations show no cost.
                </p>

                <div className="grid gap-3 sm:grid-cols-2">
                    <UsageStat
                        label="Workspaces"
                        value={data.instance_summary.workspace_count.toLocaleString()}
                    />
                    <UsageStat
                        label="Est. X spend this period"
                        value={formatMoney(
                            data.instance_summary.x_estimated_cost_usd,
                            pricingCurrency,
                        )}
                    />
                </div>
            </div>

            <section className="rounded-md border p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-sm font-medium">X API usage</h2>
                        <p className="text-sm text-muted-foreground">
                            Fetch daily Post consumption from the X Usage API
                            for the configured developer app.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={
                            !canFetchXUsage(
                                data.x_usage_available,
                                fetchXUsage.isPending,
                            )
                        }
                        onClick={() => fetchXUsage.mutate()}
                    >
                        {fetchXUsage.isPending
                            ? 'Fetching…'
                            : 'Fetch X usage'}
                    </Button>
                </div>

                {!data.x_usage_available && (
                    <p className="mt-4 text-sm text-muted-foreground">
                        Configure X_BEARER_TOKEN to enable fetching X API usage.
                    </p>
                )}

                {xUsageError && (
                    <p className="mt-4 text-sm text-destructive">
                        {xUsageError}
                    </p>
                )}

                {xUsage && (
                    <div className="mt-4 space-y-3">
                        <XCapacityMeter
                            consumed={xUsageTotal(xUsage.data)}
                            cap={xUsage.data?.project_cap ?? null}
                        />
                        <p className="text-xs text-muted-foreground">
                            {typeof xUsage.data?.cap_reset_day === 'number' &&
                                `Cap resets in ${xUsage.data.cap_reset_day} days. `}
                            Fetched {formatDate(xUsage.fetched_at)} from{' '}
                            <a
                                href={xUsage.source}
                                target="_blank"
                                rel="noreferrer"
                                className="underline underline-offset-4"
                            >
                                {xUsage.source}
                            </a>
                            .
                        </p>
                    </div>
                )}
            </section>

            <section className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="w-full sm:max-w-xs">
                        <Input
                            placeholder="Search workspaces…"
                            value={localSearch}
                            onChange={(e) =>
                                handleSearchChange(e.target.value)
                            }
                        />
                    </div>
                    <Select
                        items={sortItems}
                        value={filters.sort}
                        onValueChange={(sort) => {
                            if (sort !== null) {
                                setFilters({
                                    sort: sort as UsageFilters['sort'],
                                    page: 1,
                                });
                            }
                        }}
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Sort" />
                        </SelectTrigger>
                        <SelectContent>
                            {sortItems.map((item) => (
                                <SelectItem key={item.value} value={item.value}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="min-w-0 rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Workspace</TableHead>
                                <TableHead>Est. spend</TableHead>
                                <TableHead>Quota</TableHead>
                                <TableHead>% used</TableHead>
                                <TableHead>Change</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {workspaceUsage.data.length > 0 ? (
                                workspaceUsage.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            <button
                                                type="button"
                                                className="font-medium underline-offset-4 hover:underline"
                                                onClick={() =>
                                                    setFilters({
                                                        workspace: row.id,
                                                    })
                                                }
                                            >
                                                {row.name}
                                            </button>
                                        </TableCell>
                                        <TableCell>
                                            {formatMoney(
                                                row.x_estimated_cost_usd,
                                                pricingCurrency,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <QuotaBadge
                                                quota={row.quota}
                                                currency={pricingCurrency}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {row.percent_used === null ? (
                                                <span className="text-sm text-muted-foreground">
                                                    —
                                                </span>
                                            ) : (
                                                <PercentUsedMeter
                                                    percent={row.percent_used}
                                                />
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <CostDeltaBadge
                                                delta={row.x_cost_delta_usd}
                                                currency={pricingCurrency}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="py-6 text-center text-sm text-muted-foreground"
                                    >
                                        No workspaces match your search.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {workspaceUsage.links.length > 3 && (
                    <div className="flex flex-wrap items-center gap-1">
                        {workspaceUsage.links.map((link, index) => {
                            const page = pageFromLink(link);
                            if (page === null) {
                                return (
                                    <span
                                        key={index}
                                        className="rounded-md px-2.5 py-1 text-sm text-muted-foreground/60"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                );
                            }
                            return (
                                <button
                                    key={index}
                                    type="button"
                                    onClick={() => setFilters({ page })}
                                    className={cn(
                                        'rounded-md px-2.5 py-1 text-sm',
                                        link.active
                                            ? 'bg-foreground text-background'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                    )}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            );
                        })}
                    </div>
                )}
            </section>

            <Sheet
                open={filters.workspace !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setFilters({ workspace: null });
                    }
                }}
            >
                <SheetContent
                    side="right"
                    className="w-full gap-0 overflow-y-auto sm:max-w-xl"
                >
                    <SheetHeader>
                        <SheetTitle>
                            {drilldown?.workspace.name ?? 'Workspace'}
                        </SheetTitle>
                    </SheetHeader>

                    <div className="flex-1 space-y-8 px-6 pb-6">
                        {drilldown ? (
                            <>
                                <WorkspaceOwner
                                    owner={drilldown.workspace.owner}
                                />

                                <WorkspaceQuotaEditor
                                    workspaceId={drilldown.workspace.id}
                                    quota={drilldown.workspace.quota}
                                    locked={drilldown.workspace.is_initial}
                                />

                                <UsageTable
                                    title="Monthly counters"
                                    description="Aggregated successful usage for each recorded period."
                                    empty="No usage counters recorded yet."
                                    columns={[
                                        'Period',
                                        'Category',
                                        'Platform',
                                        'Operation',
                                        'Events',
                                        'Quota',
                                        'Est. cost',
                                        'Pricing basis',
                                    ]}
                                >
                                    {drilldown.counters.map((counter) => (
                                        <TableRow key={counter.id}>
                                            <TableCell>
                                                {counter.period_start} →{' '}
                                                {counter.period_end}
                                            </TableCell>
                                            <TableCell>
                                                {formatLabel(counter.category)}
                                            </TableCell>
                                            <TableCell>
                                                {formatPlatform(
                                                    counter.platform,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {formatLabel(counter.operation)}
                                            </TableCell>
                                            <TableCell>
                                                {counter.event_count}
                                            </TableCell>
                                            <TableCell>
                                                {counter.total_quota}
                                            </TableCell>
                                            <TableCell>
                                                {counter.pricing
                                                    ? formatMoney(
                                                          counter.pricing
                                                              .estimated_cost_usd,
                                                          pricingCurrency,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                {counter.pricing
                                                    ? `${counter.pricing.label} @ ${formatMoney(
                                                          counter.pricing
                                                              .unit_cost_usd,
                                                          pricingCurrency,
                                                      )}`
                                                    : 'Unmapped'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </UsageTable>

                                <UsageTable
                                    title="Error events"
                                    description="Latest failed usage events for debugging platform/API problems."
                                    empty="No failed usage events recorded yet."
                                    columns={[
                                        'When',
                                        'Category',
                                        'Platform',
                                        'Operation',
                                        'Quota',
                                        'Error meta',
                                    ]}
                                >
                                    {drilldown.error_events.map((event) => (
                                        <TableRow key={event.id}>
                                            <TableCell>
                                                {formatDate(event.occurred_at)}
                                            </TableCell>
                                            <TableCell>
                                                {formatLabel(event.category)}
                                            </TableCell>
                                            <TableCell>
                                                {formatPlatform(event.platform)}
                                            </TableCell>
                                            <TableCell>
                                                {formatLabel(event.operation)}
                                            </TableCell>
                                            <TableCell>
                                                {event.quota_weight}
                                            </TableCell>
                                            <TableCell className="max-w-64 truncate font-mono text-xs text-muted-foreground">
                                                {event.meta
                                                    ? JSON.stringify(event.meta)
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </UsageTable>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Loading workspace details…
                            </p>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    );
}

function pageFromLink(link: PaginationLink): number | null {
    if (link.url === null) {
        return null;
    }
    try {
        const page = new URL(link.url, window.location.origin).searchParams.get(
            'page',
        );
        return page === null ? null : Number(page);
    } catch {
        return null;
    }
}

function QuotaBadge({
    quota,
    currency,
}: {
    quota: WorkspaceQuota;
    currency: string;
}) {
    if (quota.kind === 'unlimited') {
        return <Badge variant="success">Unlimited</Badge>;
    }

    if (quota.kind === 'custom') {
        return (
            <Badge variant="outline">
                {formatMoney(quota.dollars ?? 0, currency)}/mo
            </Badge>
        );
    }

    return (
        <Badge variant="outline">
            Default {formatMoney(quota.dollars ?? 0, currency)}/mo
        </Badge>
    );
}

function XCapacityMeter({
    consumed,
    cap,
}: {
    consumed: number;
    cap: number | null;
}) {
    const percent = cap ? Math.min(100, (consumed / cap) * 100) : 0;
    const isWarning = percent > 80;

    return (
        <div className="space-y-1.5">
            <div className="flex items-baseline justify-between text-sm">
                <span className="text-muted-foreground">Posts consumed</span>
                <span
                    className={cn(
                        'font-medium tabular-nums',
                        isWarning && 'text-amber-600 dark:text-amber-500',
                    )}
                >
                    {consumed.toLocaleString()}
                    {cap !== null && ` / ${cap.toLocaleString()}`}
                </span>
            </div>
            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className={cn(
                        'h-full rounded-full transition-all',
                        isWarning ? 'bg-amber-500' : 'bg-foreground/60',
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

function PercentUsedMeter({ percent }: { percent: number }) {
    const clamped = Math.min(100, Math.max(0, percent));
    const isWarning = percent > 80;

    return (
        <div className="flex items-center gap-2">
            <div className="h-1.5 w-16 overflow-hidden rounded-full bg-muted">
                <div
                    className={cn(
                        'h-full rounded-full',
                        isWarning ? 'bg-amber-500' : 'bg-foreground/50',
                    )}
                    style={{ width: `${clamped}%` }}
                />
            </div>
            <span
                className={cn(
                    'text-xs text-muted-foreground tabular-nums',
                    isWarning && 'text-amber-600 dark:text-amber-500',
                )}
            >
                {percent}%
            </span>
        </div>
    );
}

function UsageTable({
    title,
    description,
    empty,
    columns,
    children,
}: {
    title: string;
    description: string;
    empty: string;
    columns: string[];
    children: React.ReactNode;
}) {
    const hasRows = Array.isArray(children) ? children.length > 0 : !!children;

    return (
        <section className="space-y-4">
            <div className="space-y-1">
                <h2 className="text-sm font-medium">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>

            <div className="min-w-0 rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead key={column}>{column}</TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {hasRows ? (
                            children
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="py-6 text-center text-sm text-muted-foreground"
                                >
                                    {empty}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </section>
    );
}

function UsageStat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border bg-muted/30 p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-lg font-semibold">{value}</div>
        </div>
    );
}

function WorkspaceOwner({ owner }: { owner: DrilldownOwner | null }) {
    return (
        <div className="space-y-2">
            <h3 className="text-sm font-medium">Owner</h3>
            {owner ? (
                <div className="flex items-center gap-3 rounded-md border p-3">
                    <Avatar className="size-9">
                        <AvatarImage src={owner.avatar} alt={owner.name} />
                        <AvatarFallback>{owner.name.charAt(0)}</AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <p className="truncate font-medium">{owner.name}</p>
                        <p className="truncate text-sm text-muted-foreground">
                            {owner.email}
                        </p>
                    </div>
                </div>
            ) : (
                <p className="rounded-md border p-3 text-sm text-muted-foreground">
                    This workspace has no owner assigned.
                </p>
            )}
        </div>
    );
}

function CostDeltaBadge({
    delta,
    currency,
}: {
    delta: number;
    currency: string;
}) {
    if (delta === 0) {
        return <Badge variant="outline">No change</Badge>;
    }

    const sign = delta > 0 ? '+' : '';

    return (
        <Badge variant={delta > 0 ? 'warning' : 'success'}>
            {sign}
            {formatMoney(delta, currency)}
        </Badge>
    );
}

export function formatMoney(value: number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: value === 0 ? 2 : 3,
        maximumFractionDigits: 3,
    }).format(value);
}

function formatLabel(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatPlatform(value: string | null) {
    if (!value || value === 'none') {
        return 'None';
    }

    if (value === 'x') {
        return 'X';
    }

    return formatLabel(value);
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
