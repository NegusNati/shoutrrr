<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Dto\Post\DraftData;
use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Jobs\DeletePostTarget;
use App\Models\Post;
use App\Models\PostTarget;
use App\Services\Posts\DraftService;
use App\Services\Posts\PostDuplicator;
use App\Services\Posts\PostStaleWriteException;
use App\Support\PostView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    public function __construct(private readonly DraftService $drafts) {}

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->drafts->createDraft(
            $request->user()->current_workspace_id,
            $request->user(),
            $request->validated('destination'),
            array_values(array_map(static fn (mixed $s): string => (string) ($s ?? ''), $request->validated('segments', []))),
            array_values($request->validated('mentions', [])),
            $request->validated('auto_repost'),
            DraftData::fromArray($request->validated()),
        );

        return response()->json(['post' => PostView::make($post->fresh(['targets.account', 'targets.placements', 'media']))], 201);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        try {
            $updated = $this->drafts->updateDraft($post, DraftData::fromArray($request->validated()));
        } catch (PostStaleWriteException) {
            return response()->json([
                'post' => PostView::make($post->fresh(['targets.account', 'targets.placements', 'media'])),
                'message' => 'stale_write',
            ], 409);
        }

        return response()->json(['post' => PostView::make($updated->fresh(['targets.account', 'targets.placements', 'media']))]);
    }

    public function duplicate(Request $request, Post $post, PostDuplicator $duplicator): RedirectResponse
    {
        $request->user()->can('create', Post::class) ?: abort(403);

        // Only terminal posts are eligible — mirror the client capability model
        // so the endpoint contract can't be bypassed for a draft/scheduled post.
        in_array($post->status, [
            PostStatus::Published, PostStatus::Partial, PostStatus::Failed, PostStatus::Missed,
        ], true) ?: abort(422, 'This post cannot be copied to a draft.');

        $draft = $duplicator->duplicate($post);

        return redirect()->route('posts.show', $draft)->with('success', 'Copied to a new draft.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $request->user()->can('delete', $post) ?: abort(403);

        $post->loadMissing('targets');

        $needsRemoteCleanup = in_array($post->status, [
            PostStatus::Publishing, PostStatus::Published, PostStatus::Partial, PostStatus::Failed,
        ], true);

        if (! $needsRemoteCleanup) {
            $post->delete();

            return redirect()->route('posts.index')->with('success', 'Post deleted.');
        }

        $targetsToDelete = DB::transaction(function () use ($post) {
            $targetsToDelete = $post->targets
                ->filter(fn (PostTarget $target): bool => $this->hasRemotePosts($target))
                ->values();

            $targetsToDelete->each(fn (PostTarget $target) => $target->forceFill([
                'status' => PostTargetStatus::Deleting->value,
                'next_attempt_at' => null,
            ])->save());

            $post->targets
                ->reject(fn (PostTarget $target): bool => $this->hasRemotePosts($target))
                ->each(fn (PostTarget $target) => $target->forceFill([
                    'status' => PostTargetStatus::Deleted->value,
                    'next_attempt_at' => null,
                ])->save());

            $post->forceFill([
                'status' => PostStatus::Deleted->value,
                'deleted_at' => now(),
            ])->save();

            return $targetsToDelete;
        });

        $targetsToDelete->each(fn (PostTarget $target) => DeletePostTarget::dispatch($target));

        return redirect()->route('posts.index')->with('success', 'Post deleted from connected accounts where possible.');
    }

    private function hasRemotePosts(PostTarget $target): bool
    {
        return $target->remote_id !== null || ($target->remote_ids ?? []) !== [];
    }
}
