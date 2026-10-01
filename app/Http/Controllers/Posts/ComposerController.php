<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Http\Controllers\WorkspaceMentionController;
use App\Models\AccountSet;
use App\Models\ConnectedAccount;
use App\Models\Post;
use App\Models\WorkspaceMention;
use App\Support\InstanceSettings;
use App\Support\PostView;
use Illuminate\Http\Request;

class ComposerController extends Controller
{
    /**
     * Shared payload for the SPA's composer endpoint — post view, selectable
     * accounts/sets, platform limits, and the workspace's saved mentions.
     * `stats` is served separately by the metrics endpoint.
     *
     * @return array{post: array<string, mixed>, accounts: array<int, array<string, mixed>>, sets: array<int, array<string, mixed>>, limits: array<int, mixed>, savedMentions: array<int, array<string, mixed>>, metricsEnabled: bool}
     */
    protected function composePayload(Request $request, Post $post): array
    {
        $defaultAccountId = $request->user()->currentWorkspace()->value('default_connected_account_id');
        $settings = app(InstanceSettings::class);

        $accounts = ConnectedAccount::query()
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

        $sets = AccountSet::query()
            ->with('accounts:id')
            ->get()
            ->map(fn (AccountSet $set): array => [
                'id' => $set->id,
                'name' => $set->name,
                'connected_account_ids' => $set->accounts->pluck('id')->all(),
            ])->all();

        return [
            'post' => PostView::make($post->load(['targets.account', 'targets.placements', 'media'])),
            'accounts' => $accounts,
            'sets' => $sets,
            'limits' => Platform::allLimits(),
            'savedMentions' => WorkspaceMention::withoutGlobalScopes()
                ->where('workspace_id', $request->user()->current_workspace_id)
                ->orderBy('name')
                ->get()
                ->map(fn (WorkspaceMention $mention): array => WorkspaceMentionController::view($mention))
                ->all(),
            'metricsEnabled' => $settings->metricsEnabled(),
        ];
    }
}
