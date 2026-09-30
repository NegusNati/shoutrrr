<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Platform;
use App\Enums\ReplyStatus;
use App\Models\AccountSet;
use App\Models\ConnectedAccount;
use App\Models\Conversation;
use App\Models\PostTargetReply;
use App\Models\User;
use App\Models\WorkspaceMembership;
use App\Services\Gifs\KlipyClient;
use App\Support\Notifications\NotificationPresenter;

/**
 * Data shared by the web app shell: workspaces, sidebar shell data, unread
 * badges, billing/community/update metadata. Both HandleInertiaRequests
 * (legacy Inertia frontend) and the JSON API `/me` endpoint (consumed by the
 * standalone SPA) consume this service so the two surfaces expose the same
 * data.
 *
 * The members are exposed individually (accounts(), sets(), unreadReplies(),
 * ...) so lazy callers can resolve only what they need; shell() composes
 * them for eager consumers.
 */
class AppShellData
{
    /**
     * Shell data needed by the sidebar, composer, and command palette on nearly
     * every page.
     *
     * @return array{
     *     accounts: array<int, array<string, mixed>>,
     *     sets: array<int, array<string, mixed>>,
     *     limits: list<array<string, mixed>>,
     *     unreadReplies: int,
     *     unreadMessages: int,
     *     gifs_enabled: bool,
     * }
     */
    public function shell(?User $user): array
    {
        return [
            'accounts' => $this->accounts($user),
            'sets' => $this->sets($user?->current_workspace_id),
            'limits' => Platform::allLimits(),
            'unreadReplies' => $this->unreadReplies($user),
            'unreadMessages' => $this->unreadMessages($user),
            'gifs_enabled' => $this->gifsEnabled(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function accounts(?User $user): array
    {
        $workspaceId = $user?->current_workspace_id;

        if (! $user || ! $workspaceId) {
            return [];
        }

        $settings = app(InstanceSettings::class);
        $defaultAccountId = $user->currentWorkspace()->value('default_connected_account_id');

        return ConnectedAccount::query()
            ->where('workspace_id', $workspaceId)
            ->enabled()
            ->get()
            ->filter(fn (ConnectedAccount $account): bool => $settings->platformAvailable($account->platform))
            ->sortByDesc(fn (ConnectedAccount $account): bool => $account->id === $defaultAccountId)
            ->map(fn (ConnectedAccount $account): array => [
                'id' => $account->id,
                'platform' => $account->platform->value,
                'handle' => $account->handle,
                'display_name' => $account->display_name,
                'avatar_url' => $account->avatar_url,
                'status' => $account->status->value,
                'max_text_length' => $account->maxTextLength(),
                'max_video_duration_seconds' => $account->maxVideoDurationSeconds(),
                'x_premium' => $account->hasXPremium(),
                'auto_repost_enabled' => $account->autoRepostEnabled(),
            ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sets(?string $workspaceId): array
    {
        if (! $workspaceId) {
            return [];
        }

        return AccountSet::query()
            ->where('workspace_id', $workspaceId)
            ->with('accounts:id')
            ->get()
            ->map(fn (AccountSet $set): array => [
                'id' => $set->id,
                'name' => $set->name,
                'connected_account_ids' => $set->accounts->pluck('id')->all(),
            ])->values()->all();
    }

    public function unreadReplies(?User $user): int
    {
        $workspaceId = $user?->current_workspace_id;
        $settings = app(InstanceSettings::class);

        return $workspaceId
            && $settings->engagementEnabled()
            && $settings->engagementPollingEnabled()
                ? PostTargetReply::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('is_ours', false)
                    ->where('status', '!=', ReplyStatus::Archived->value)
                    ->whereNull('read_at')
                    ->count()
                : 0;
    }

    public function unreadMessages(?User $user): int
    {
        $workspaceId = $user?->current_workspace_id;

        return $workspaceId && app(InstanceSettings::class)->messagesEnabled()
            ? (int) Conversation::query()
                ->where('workspace_id', $workspaceId)
                ->whereNull('archived_at')
                ->sum('unread_count')
            : 0;
    }

    public function gifsEnabled(): bool
    {
        return app(KlipyClient::class)->configured();
    }

    /**
     * @return array<string, mixed>
     */
    public function workspaces(?User $user): array
    {
        $enabled = (bool) config('kit.workspaces.enabled');
        $canCreate = $enabled && app(InstanceSettings::class)->workspaceCreationEnabled();

        if (! $user) {
            return [
                'enabled' => $enabled,
                'all' => [],
                'current' => null,
                'canCreateWorkspaces' => $canCreate,
            ];
        }

        $memberships = $user->workspaceMemberships()->with('workspace.postingSchedule')->get();
        // Cache the eager-loaded memberships on the user so billing() can reuse
        // them within the same request instead of issuing a second query.
        $user->setRelation('workspaceMemberships', $memberships);

        $all = $memberships->map(fn (WorkspaceMembership $m) => [
            'id' => $m->workspace->id,
            'name' => $m->workspace->name,
            'role' => $m->role->value,
            'logo' => $m->workspace->logo,
        ])->values()->all();

        $current = null;
        if ($user->current_workspace_id) {
            $membership = $memberships->firstWhere('workspace_id', $user->current_workspace_id);

            if ($membership) {
                $current = [
                    'id' => $membership->workspace->id,
                    'name' => $membership->workspace->name,
                    'role' => $membership->role->value,
                    'logo' => $membership->workspace->logo,
                    'permissions' => $membership->permissions,
                    'timezone' => $membership->workspace->postingSchedule->timezone ?? 'UTC',
                ];
            }
        }

        return [
            'enabled' => $enabled,
            'all' => $all,
            'current' => $current,
            'canCreateWorkspaces' => $canCreate,
        ];
    }

    /**
     * @return array{subscribed: bool, manageUrl: string}|null
     */
    public function billing(?User $user): ?array
    {
        if (! config('subscriptions.enabled') || ! $user || ! $user->current_workspace_id) {
            return null;
        }

        // Reuse the memberships eager-loaded by workspaces() (which runs earlier
        // in the same request); fall back to a scoped query if they aren't loaded.
        $membership = $user->relationLoaded('workspaceMemberships')
            ? $user->workspaceMemberships->firstWhere('workspace_id', $user->current_workspace_id)
            : $user->workspaceMemberships()
                ->with('workspace')
                ->where('workspace_id', $user->current_workspace_id)
                ->first();

        if (! $membership || ! in_array('workspace.billing.manage', $membership->permissions, true)) {
            return null;
        }

        return [
            'subscribed' => $membership->workspace->subscribed('default'),
            'manageUrl' => route('billing.index'),
        ];
    }

    /**
     * @return array{repoUrl: string, sponsorUrl: string, stars: ?int}|null
     */
    public function community(): ?array
    {
        if (config('subscriptions.enabled')) {
            return null;
        }

        $repo = (string) config('instance.community.repo');

        return [
            'repoUrl' => "https://github.com/{$repo}",
            'sponsorUrl' => (string) config('instance.community.sponsor_url'),
            'stars' => CommunityStats::stars(),
        ];
    }

    /**
     * @return array{updateAvailable: bool, latestVersion: ?string, latestReleaseUrl: ?string}
     */
    public function update(): array
    {
        if (config('subscriptions.enabled') || ! CommunityStats::updateAvailable()) {
            return ['updateAvailable' => false, 'latestVersion' => null, 'latestReleaseUrl' => null];
        }

        $latest = CommunityStats::latestVersion();
        $repo = (string) config('instance.community.repo');

        return [
            'updateAvailable' => true,
            'latestVersion' => $latest,
            'latestReleaseUrl' => "https://github.com/{$repo}/releases/tag/{$latest}",
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, unreadCount: int}
     */
    public function notifications(?User $user): array
    {
        if ($user === null) {
            return ['items' => [], 'unreadCount' => 0];
        }

        return NotificationPresenter::collection($user, $user->current_workspace_id);
    }
}
