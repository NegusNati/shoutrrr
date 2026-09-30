<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

/**
 * Session-only instance settings. Mirrors the Inertia
 * Settings\InstanceSettingsController — same reads and writes shaped as JSON.
 * The usage drilldown becomes its own endpoint instead of a `?workspace=`
 * Inertia prop, and web `back()->withErrors()` flashes become 422 JSON.
 */
class InstanceSettingsController extends Controller
{
    public function show(Request $request, InstanceSettings $settings): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        return response()->json($this->settingsPayload($settings));
    }

    public function update(UpdateInstanceSettingsRequest $request, InstanceSettings $settings): JsonResponse
    {
        $settings->update($request->instanceSettings());

        return response()->json($this->settingsPayload($settings));
    }

    public function polling(Request $request, InstanceSettings $settings): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        return response()->json($this->pollingPayload($settings));
    }

    public function updatePolling(UpdateInstancePollingSettingsRequest $request, InstanceSettings $settings): JsonResponse
    {
        $settings->update($request->instancePollingSettings());

        return response()->json($this->pollingPayload($settings));
    }

    public function platforms(Request $request, InstanceSettings $settings): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        return response()->json($this->platformsPayload($settings));
    }

    public function updatePlatforms(UpdateInstancePlatformsRequest $request, InstanceSettings $settings): JsonResponse
    {
        $settings->update([
            'platforms_enabled' => $request->platformsEnabled(),
            'linkedin_community_management_enabled' => $request->linkedinCommunityManagementEnabled(),
        ]);

        return response()->json($this->platformsPayload($settings));
    }

    public function usage(Request $request, UsagePricing $pricing, InstanceSettings $settings, WorkspaceSubscriptionGate $gate): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        $search = $request->string('search')->trim()->toString();
        $sort = $request->string('sort')->toString() === 'name' ? 'name' : 'spend';

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

        return response()->json([
            'filters' => ['search' => $search === '' ? null : $search, 'sort' => $sort],
            'instance_summary' => [
                'workspace_count' => Workspace::query()->count(),
                'x_estimated_cost_usd' => $this->estimateCountersCost($pricing, $instanceXRows),
            ],
            'workspace_usage' => $workspaces,
            'pricing_source' => config('usage_pricing.source_url'),
            'pricing_currency' => config('usage_pricing.platforms.x.currency', 'USD'),
            'x_usage_available' => (string) config('services.x.bearer_token', '') !== '',
        ]);
    }

    /**
     * Per-workspace usage detail — counters, failed events, and the X quota.
     * The Inertia version folded this into the usage page as a `?workspace=`
     * prop; here it is its own resource the SPA drills into.
     */
    public function workspaceUsage(Request $request, Workspace $workspace, UsagePricing $pricing, WorkspaceSubscriptionGate $gate, InstanceSettings $settings): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        $workspace->loadMissing('owner:id,name,email,avatar_path');

        $defaultDollars = (int) config('subscriptions.monthly_x_budget_cents') / 100;

        $counters = array_values(UsagePeriodCounter::query()
            ->where('workspace_id', $workspace->id)
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
            ->where('workspace_id', $workspace->id)
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

        return response()->json([
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
        ]);
    }

    public function updateWorkspaceBudget(
        UpdateWorkspaceXBudgetRequest $request,
        Workspace $workspace,
        InstanceSettings $settings,
        WorkspaceSubscriptionGate $gate,
    ): JsonResponse {
        $settings->setXWorkspaceBudget($workspace->id, $request->budgetValue());

        $defaultDollars = (int) config('subscriptions.monthly_x_budget_cents') / 100;

        return response()->json([
            'quota' => $this->xQuotaFor($workspace, $gate, $settings, $defaultDollars),
        ]);
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

    public function admins(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);

        $search = $request->string('search')->trim()->toString();

        $users = $search === ''
            ? collect()
            : User::query()
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

        return response()->json([
            'owners' => User::query()
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
                ]),
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function storeAdmin(StoreInstanceOwnerRequest $request): JsonResponse
    {
        User::query()
            ->where('email', $request->email())
            ->update(['instance_role' => InstanceRole::Owner->value]);

        $owner = User::query()->where('email', $request->email())->firstOrFail();

        return response()->json([
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'avatar' => $owner->avatar,
                'created_at' => $owner->created_at,
            ],
        ], 201);
    }

    public function destroyAdmin(Request $request, User $owner): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);
        abort_unless($owner->isInstanceOwner(), 404);

        if ($owner->is($user)) {
            return response()->json([
                'message' => 'You cannot remove yourself as an instance owner.',
                'errors' => ['owner' => ['You cannot remove yourself as an instance owner.']],
            ], 422);
        }

        if (User::query()->where('instance_role', InstanceRole::Owner->value)->count() <= 1) {
            return response()->json([
                'message' => 'At least one instance owner is required.',
                'errors' => ['owner' => ['At least one instance owner is required.']],
            ], 422);
        }

        $owner->update(['instance_role' => null]);

        return response()->json(['removed' => true]);
    }

    /**
     * @return array{settings: array{registrations_enabled: bool, workspace_creation_enabled: bool, usage_tracking_enabled: bool, quote_tweets_enabled: bool}, workspaces_enabled: bool}
     */
    private function settingsPayload(InstanceSettings $settings): array
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

    /**
     * @return array{settings: array<string, mixed>, sections: array<string, list<array{platform: string, label: string}>>}
     */
    private function pollingPayload(InstanceSettings $settings): array
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

    /**
     * @return array{platforms: list<array{platform: string, label: string, enabled: bool, configured: bool}>, linkedin_community_management_enabled: bool}
     */
    private function platformsPayload(InstanceSettings $settings): array
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

    /**
     * @return array{kind: string, dollars: float|null}
     */
    private function xQuotaFor(Workspace $workspace, WorkspaceSubscriptionGate $gate, InstanceSettings $settings, float $defaultDollars): array
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

    private function estimateCountersCost(UsagePricing $pricing, mixed $counters): float
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
}
