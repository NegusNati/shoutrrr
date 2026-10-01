<?php

namespace App\Http\Controllers\Settings;

use App\Enums\InstanceRole;
use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreInstanceOwnerRequest;
use App\Http\Requests\Settings\UpdateInstancePlatformsRequest;
use App\Http\Requests\Settings\UpdateInstancePollingSettingsRequest;
use App\Http\Requests\Settings\UpdateInstanceSettingsRequest;
use App\Http\Requests\Settings\UpdateWorkspaceXBudgetRequest;
use App\Models\UsageEvent;
use App\Models\UsagePeriodCounter;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceSubscriptionGate;
use App\Support\InstanceSettings;
use App\Support\UsagePricing;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class InstanceSettingsController extends Controller
{
    public function edit(Request $request, InstanceSettings $settings): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        return Inertia::render('settings/instance', $this->instancePayload($settings));
    }

    /**
     * @return array{settings: array<string, mixed>, workspaces_enabled: bool}
     */
    protected function instancePayload(InstanceSettings $settings): array
    {
        $workspacesEnabled = (bool) config('kit.workspaces.enabled');
        $instanceSettings = $settings->all();

        if (! $workspacesEnabled) {
            $instanceSettings['workspace_creation_enabled'] = false;
        }

        return [
            'settings' => $instanceSettings,
            'workspaces_enabled' => $workspacesEnabled,
        ];
    }

    public function admins(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        $search = $request->string('search')->trim()->toString();
        $users = $this->searchableUsers($search);

        return Inertia::render('settings/instance-admins', [
            'owners' => $this->instanceOwners(),
            'users' => $users,
            'search' => $search,
        ]);
    }

    /**
     * @return Collection<int, array{id: string, name: string, email: string, avatar: string, created_at: Carbon|null}>
     */
    protected function instanceOwners(): Collection
    {
        return User::query()
            ->select(['id', 'name', 'email', 'created_at'])
            ->where('instance_role', InstanceRole::Owner->value)
            ->orderBy('email')
            ->get()
            ->map(fn (User $owner): array => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'avatar' => $owner->avatar,
                'created_at' => $owner->created_at,
            ]);
    }

    /**
     * @return Collection<int, array{id: string, name: string, email: string, avatar: string}>
     */
    protected function searchableUsers(string $search): Collection
    {
        if ($search === '') {
            return collect();
        }

        return User::query()
            ->select(['id', 'name', 'email'])
            ->whereNull('instance_role')
            ->whereLike('email', "%{$search}%")
            ->orderBy('email')
            ->limit(10)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ]);
    }

    public function polling(Request $request, InstanceSettings $settings): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        return Inertia::render('settings/instance-polling', $this->pollingPayload($settings));
    }

    /**
     * @return array{settings: array<string, mixed>, sections: array<string, list<array{platform: string, label: string}>>}
     */
    protected function pollingPayload(InstanceSettings $settings): array
    {
        return [
            'settings' => $settings->polling(),
            'sections' => collect(['engagement', 'post_metrics', 'account_metrics'])
                ->mapWithKeys(fn (string $section): array => [
                    $section => array_map(
                        fn (Platform $platform): array => [
                            'platform' => $platform->value,
                            'label' => $platform->label(),
                        ],
                        Platform::pollingSectionPlatforms($section),
                    ),
                ])->all(),
        ];
    }

    public function platforms(Request $request, InstanceSettings $settings): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        return Inertia::render('settings/instance-platforms', $this->platformsPayload($settings));
    }

    /**
     * @return array{platforms: list<array{platform: string, label: string, enabled: bool, configured: bool}>, linkedin_community_management_enabled: bool}
     */
    protected function platformsPayload(InstanceSettings $settings): array
    {
        $enabled = $settings->platformsEnabled();

        return [
            'platforms' => array_map(fn (Platform $platform): array => [
                'platform' => $platform->value,
                'label' => $platform->label(),
                'enabled' => $enabled[$platform->value] ?? true,
                'configured' => $platform->isConfigured(),
            ], Platform::cases()),
            'linkedin_community_management_enabled' => $settings->linkedinCommunityManagementEnabled(),
        ];
    }

    public function updatePlatforms(UpdateInstancePlatformsRequest $request, InstanceSettings $settings): RedirectResponse
    {
        $settings->update([
            'platforms_enabled' => $request->platformsEnabled(),
            'linkedin_community_management_enabled' => $request->linkedinCommunityManagementEnabled(),
        ]);

        return back()->with('success', 'Platform settings updated.');
    }

    public function usage(Request $request, UsagePricing $pricing, InstanceSettings $settings, WorkspaceSubscriptionGate $gate): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        $search = $request->string('search')->trim()->toString();
        $sort = $request->string('sort')->toString() === 'name' ? 'name' : 'spend';
        $workspaceId = $request->string('workspace')->trim()->toString() ?: null;
        $defaultDollars = (int) config('subscriptions.monthly_x_budget_cents') / 100;

        return Inertia::render('settings/instance-usage', [
            ...$this->usagePayload($request, $pricing, $settings, $gate),
            ...($workspaceId === null
                ? []
                : ['drilldown' => fn () => $this->workspaceDrilldown($workspaceId, $pricing, $gate, $settings, $defaultDollars)]),
        ]);
    }

    /**
     * Everything the usage report shares between the Inertia page and the API:
     * filters echo, per-workspace rows, and the instance-wide summary. The
     * drilldown stays out — Inertia requests it lazily via `only`, the API
     * calls workspaceDrilldown() eagerly on a `?workspace=` query.
     *
     * @return array{filters: array{search: ?string, sort: string, workspace: ?string}, instance_summary: array{workspace_count: int, x_estimated_cost_usd: float}, workspace_usage: LengthAwarePaginator<int, array{id: string, name: string, x_estimated_cost_usd: float, x_previous_cost_usd: float, x_cost_delta_usd: float, quota: array{kind: string, dollars: float|null}, percent_used: float|null}>, pricing_source: ?string, pricing_currency: string, x_usage_available: bool}
     */
    protected function usagePayload(Request $request, UsagePricing $pricing, InstanceSettings $settings, WorkspaceSubscriptionGate $gate): array
    {
        $search = $request->string('search')->trim()->toString();
        $sort = $request->string('sort')->toString() === 'name' ? 'name' : 'spend';
        $workspaceId = $request->string('workspace')->trim()->toString() ?: null;

        $now = CarbonImmutable::instance(Date::now());
        $currentPeriodStart = $now->startOfMonth()->toDateString();
        $previousPeriodStart = $now->subMonthNoOverflow()->startOfMonth()->toDateString();
        $defaultDollars = (int) config('subscriptions.monthly_x_budget_cents') / 100;

        $query = Workspace::query()
            ->select(['id', 'name', 'is_initial'])
            ->when($search !== '', fn ($q) => $q->whereLike('name', "%{$search}%"))
            ->withSum(['usagePeriodCounters as x_cost_sum' => fn ($q) => $q
                ->where('platform', Platform::X->value)
                ->where('period_start', $currentPeriodStart)], 'total_cost_microusd')
            ->when($sort === 'name', fn ($q) => $q->orderBy('name'), fn ($q) => $q->orderByDesc('x_cost_sum')->orderBy('name'));

        $workspaces = $query->paginate(20)->withQueryString()->through(function (Workspace $workspace) use ($gate, $settings, $pricing, $previousPeriodStart, $defaultDollars): array {
            $currentCostUsd = round($gate->currentXCostMicrousd($workspace) / 1_000_000, 6);
            $budgetMicrousd = $gate->monthlyXBudgetMicrousd($workspace);

            $previousRows = UsagePeriodCounter::query()
                ->where('workspace_id', $workspace->id)
                ->where('platform', Platform::X->value)
                ->where('period_start', $previousPeriodStart)
                ->get();
            $previousCostUsd = $this->estimateCountersCost($pricing, $previousRows);

            return [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'x_estimated_cost_usd' => $currentCostUsd,
                'x_previous_cost_usd' => $previousCostUsd,
                'x_cost_delta_usd' => round($currentCostUsd - $previousCostUsd, 6),
                'quota' => $this->xQuotaFor($workspace, $gate, $settings, $defaultDollars),
                'percent_used' => ($budgetMicrousd === null || $budgetMicrousd === 0)
                    ? null
                    : round(($currentCostUsd * 1_000_000) / $budgetMicrousd * 100, 1),
            ];
        });

        $instanceXRows = UsagePeriodCounter::query()
            ->where('platform', Platform::X->value)
            ->where('period_start', $currentPeriodStart)
            ->get();

        // Renamed from 'instance' to avoid colliding with the shared top-level
        // `instance` prop (HandleInertiaRequests::share -> instance.isOwner),
        // which Inertia would otherwise merge over with this page's array.
        //
        // Note on time windows: this header total is calendar-month
        // (UsagePeriodCounter, period_start = start of this month), while a
        // subscribed workspace's per-row x_estimated_cost_usd below is
        // billing-cycle-anchored (WorkspaceSubscriptionGate::currentXCostMicrousd).
        // Under subscriptions.enabled the sum of rows may not equal this total;
        // they reconcile exactly when subscriptions are disabled, since both are
        // calendar-month in that case.
        return [
            'filters' => ['search' => $search === '' ? null : $search, 'sort' => $sort, 'workspace' => $workspaceId],
            'instance_summary' => [
                'workspace_count' => Workspace::query()->count(),
                'x_estimated_cost_usd' => $this->estimateCountersCost($pricing, $instanceXRows),
            ],
            'workspace_usage' => $workspaces,
            'pricing_source' => config('usage_pricing.source_url'),
            'pricing_currency' => config('usage_pricing.platforms.x.currency', 'USD'),
            'x_usage_available' => (string) config('services.x.bearer_token', '') !== '',
        ];
    }

    /**
     * @return array{workspace: array{id: string, name: string, quota: array{kind: string, dollars: float|null}}, counters: list<array<string, mixed>>, error_events: list<array<string, mixed>>}|null
     */
    protected function workspaceDrilldown(string $workspaceId, UsagePricing $pricing, WorkspaceSubscriptionGate $gate, InstanceSettings $settings, float $defaultDollars): ?array
    {
        $workspace = Workspace::query()
            ->select(['id', 'name', 'is_initial', 'owner_id'])
            ->with('owner:id,name,email,avatar_path')
            ->find($workspaceId);

        if ($workspace === null) {
            return null;
        }

        $counters = array_values(UsagePeriodCounter::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('period_start')->orderBy('category')->orderBy('platform')->orderBy('operation')
            ->get()
            ->map(fn (UsagePeriodCounter $counter): array => [
                'id' => $counter->id,
                'period_start' => $counter->period_start,
                'period_end' => $counter->period_end,
                'category' => $counter->category,
                'platform' => $counter->platform,
                'operation' => $counter->operation,
                'event_count' => $counter->event_count,
                'total_quota' => $counter->total_quota,
                'pricing' => $pricing->estimate($counter->platform, $counter->operation, $counter->total_quota),
            ])->all());

        $errorEvents = array_values(UsageEvent::query()
            ->where('workspace_id', $workspaceId)
            ->where('succeeded', false)
            ->latest('occurred_at')->limit(50)->get()
            ->map(fn (UsageEvent $event): array => [
                'id' => $event->id,
                'category' => $event->category,
                'operation' => $event->operation,
                'platform' => $event->platform ?? 'none',
                'quota_weight' => $event->quota_weight,
                'meta' => $event->meta,
                'occurred_at' => $event->occurred_at,
            ])->all());

        return [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'is_initial' => $workspace->is_initial,
                'quota' => $this->xQuotaFor($workspace, $gate, $settings, $defaultDollars),
                'owner' => $workspace->owner === null ? null : [
                    'name' => $workspace->owner->name,
                    'email' => $workspace->owner->email,
                    'avatar' => $workspace->owner->avatar,
                ],
            ],
            'counters' => $counters,
            'error_events' => $errorEvents,
        ];
    }

    /**
     * @return array{kind: string, dollars: float|null}
     */
    protected function xQuotaFor(Workspace $workspace, WorkspaceSubscriptionGate $gate, InstanceSettings $settings, float $defaultDollars): array
    {
        $override = $settings->xWorkspaceBudget($workspace->id);
        // Reuse the gate's single definition of "no X ceiling" so the badge shown
        // to owners can never disagree with what the gate actually enforces.
        $unlimited = $gate->isXUnlimited($workspace);

        return [
            'kind' => $unlimited ? 'unlimited' : (is_int($override) ? 'custom' : 'default'),
            'dollars' => $unlimited ? null : (is_int($override) ? $override / 100 : $defaultDollars),
        ];
    }

    public function xUsage(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        $bearerToken = (string) config('services.x.bearer_token', '');

        if ($bearerToken === '') {
            return response()->json([
                'message' => 'Configure X_BEARER_TOKEN before fetching X API usage.',
            ], 422);
        }

        $days = (int) ($validated['days'] ?? 7);
        $cacheKey = 'instance-settings:x-usage:tweets:'.sha1($bearerToken).":{$days}";

        try {
            /** @var array{data: mixed, fetched_at: string, source: string} $usage */
            $usage = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($bearerToken, $days): array {
                $response = Http::withToken($bearerToken)
                    ->acceptJson()
                    ->timeout(10)
                    ->get('https://api.x.com/2/usage/tweets', [
                        'days' => $days,
                        'usage.fields' => 'cap_reset_day,daily_client_app_usage,daily_project_usage,project_cap,project_id,project_usage',
                    ]);

                if ($response->failed()) {
                    $response->throw();
                }

                return [
                    'data' => $response->json('data'),
                    'fetched_at' => Date::now()->toIso8601String(),
                    'source' => 'https://api.x.com/2/usage/tweets',
                ];
            });
        } catch (RequestException $exception) {
            $response = $exception->response;

            return response()->json([
                'message' => 'Unable to fetch X API usage.',
                'status' => $response->status(),
                'error' => $response->json() ?? $response->body(),
            ], 502);
        }

        return response()->json($usage);
    }

    protected function estimateCountersCost(UsagePricing $pricing, mixed $counters): float
    {
        $total = 0.0;

        foreach ($counters as $counter) {
            if (! $counter instanceof UsagePeriodCounter) {
                continue;
            }

            $estimate = $pricing->estimate($counter->platform, $counter->operation, $counter->total_quota);

            if ($estimate !== null) {
                $total += $estimate['estimated_cost_usd'];
            }
        }

        return round($total, 6);
    }

    public function update(UpdateInstanceSettingsRequest $request, InstanceSettings $settings): RedirectResponse
    {
        $settings->update($request->instanceSettings());

        return back()->with('success', 'Instance settings updated.');
    }

    public function updatePolling(UpdateInstancePollingSettingsRequest $request, InstanceSettings $settings): RedirectResponse
    {
        $settings->update($request->instancePollingSettings());

        return back()->with('success', 'Polling settings updated.');
    }

    public function updateWorkspaceBudget(
        UpdateWorkspaceXBudgetRequest $request,
        Workspace $workspace,
        InstanceSettings $settings,
    ): RedirectResponse {
        $settings->setXWorkspaceBudget($workspace->id, $request->budgetValue());

        return back()->with('success', 'Workspace X budget updated.');
    }

    public function destroyAdmin(Request $request, User $owner): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);
        abort_unless($owner->isInstanceOwner(), 404);

        if (($error = $this->ownerRemovalError($user, $owner)) !== null) {
            return back()->withErrors(['owner' => $error]);
        }

        $owner->update(['instance_role' => null]);

        return back()->with('success', 'Instance owner removed.');
    }

    /**
     * The reason an owner removal is refused, or null when it may proceed.
     * Shared by the web redirect flow and the JSON API.
     */
    protected function ownerRemovalError(User $actor, User $owner): ?string
    {
        if ($owner->is($actor)) {
            return 'You cannot remove yourself as an instance owner.';
        }

        if (User::query()->where('instance_role', InstanceRole::Owner->value)->count() <= 1) {
            return 'At least one instance owner is required.';
        }

        return null;
    }

    public function storeAdmin(StoreInstanceOwnerRequest $request): RedirectResponse
    {
        User::query()
            ->where('email', $request->email())
            ->update(['instance_role' => InstanceRole::Owner->value]);

        return back()->with('success', 'Instance owner added.');
    }
}
