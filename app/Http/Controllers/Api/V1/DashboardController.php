<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WorkspaceMentionController;
use App\Models\Post;
use App\Models\Workspace;
use App\Models\WorkspaceMention;
use App\Support\Onboarding\OnboardingPresenter;
use App\Support\PostListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The compose-first home payload: onboarding state, saved mentions, and the
 * recent-posts feed — the JSON equivalent of the web dashboard page.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace;

        return response()->json([
            'onboarding' => $workspace instanceof Workspace
                ? OnboardingPresenter::make($workspace, $user)
                : null,
            'savedMentions' => $user?->current_workspace_id
                ? WorkspaceMention::withoutGlobalScopes()
                    ->where('workspace_id', $user->current_workspace_id)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (WorkspaceMention $mention): array => WorkspaceMentionController::view($mention))
                    ->all()
                : [],
            'posts' => Post::query()
                ->with(['author:id,name', 'targets', 'media'])
                ->latest('updated_at')
                ->limit(25)
                ->get()
                ->map(fn (Post $post): array => PostListItem::make($post))
                ->all(),
        ]);
    }
}
