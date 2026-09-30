<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Dto\Post\DraftData;
use App\Enums\PostStatus;
use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspacePost;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\Concerns\PostPayloadRules;
use App\Jobs\DeletePostTarget;
use App\Models\AccountSet;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\User;
use App\Services\Posts\DraftService;
use App\Services\Posts\PostDuplicator;
use App\Services\Posts\PostStaleWriteException;
use App\Support\CursorPage;
use App\Support\PostListItem;
use App\Support\PostView;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

class PostsController extends Controller
{
    use PostPayloadRules;
    use ResolvesWorkspacePost;

    /**
     * List the workspace's posts with the same status/set/platform/search
     * filters and per-status counts the web index computes, so the SPA's filter
     * tabs stay consistent with the list beneath them.
     */
    #[QueryParameter('cursor', 'Cursor for the next page, from pagination.next_cursor.', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:all,draft,scheduled,publishing,published,partial,failed,deleted,missed'],
            'set' => ['nullable', 'string'],
            'platform' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:latest,timeline'],
            'cursor' => ['nullable', 'string'],
        ]);

        $status = (string) ($validated['status'] ?? '');
        $set = (string) ($validated['set'] ?? '');
        $platform = (string) ($validated['platform'] ?? '');
        $q = (string) ($validated['q'] ?? '');

        // The status-tab counts and the paginated list share the same set/
        // platform/search predicates (only the status filter differs), so
        // define them once to keep the two queries from drifting apart.
        $applyFilters = fn ($query) => $query
            ->when($set !== '', fn ($q2) => $q2->where('account_set_id', $set))
            ->when($platform !== '', fn ($q2) => $q2->whereHas('targets',
                fn ($t) => $t->where('platform', $platform)))
            ->when($q !== '', fn ($q2) => $q2->whereLike('base_text', "%{$q}%"));

        $byStatus = $applyFilters(
            Post::query()->where('status', '!=', PostStatus::Deleted->value)
        )
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [
            'all' => (int) $byStatus->sum(),
            'scheduled' => (int) ($byStatus[PostStatus::Scheduled->value] ?? 0),
            'draft' => (int) ($byStatus[PostStatus::Draft->value] ?? 0),
            'published' => (int) ($byStatus[PostStatus::Published->value] ?? 0),
            'missed' => (int) ($byStatus[PostStatus::Missed->value] ?? 0),
        ];

        $sets = AccountSet::query()->get(['id', 'name'])
            ->map(fn (AccountSet $s): array => ['id' => $s->id, 'name' => $s->name])->all();

        $posts = $applyFilters(
            Post::query()
                ->with(['author:id,name', 'targets', 'media'])
                ->where('status', '!=', PostStatus::Deleted->value)
                ->when($status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
        );

        // 'timeline' orders by the effective posting date (scheduled_at falling
        // back to created_at) like the web list; 'latest' is plain id order.
        // Cursor pagination can't build its keyset WHERE clause from a raw
        // expression orderBy, so the COALESCE is aliased and ordered by alias,
        // with id as the unique tiebreaker.
        if (($validated['sort'] ?? 'timeline') === 'timeline') {
            $posts
                ->select('posts.*')
                ->selectRaw('COALESCE(scheduled_at, created_at) as sort_key')
                ->orderByDesc('sort_key')
                ->orderByDesc('id');
        } else {
            $posts->orderByDesc('id');
        }

        // The cursor's keyset is (sort_key, id), so both must survive the item
        // transform — carry the computed sort_key alongside each row.
        $paginator = $posts
            ->cursorPaginate($validated['per_page'] ?? 25)
            ->withQueryString()
            ->through(fn (Post $post): array => [
                ...PostListItem::make($post),
                'sort_key' => $post->getAttribute('sort_key'),
            ]);

        return response()->json([
            ...CursorPage::make($paginator),
            'meta' => [
                'counts' => $counts,
                'sets' => $sets,
                'filters' => [
                    'status' => $status !== '' ? $status : 'all',
                    'set' => $set,
                    'platform' => $platform,
                    'q' => $q,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $model = $this->findPostOrFail($id);

        return response()->json(['post' => PostView::make($model->load(['targets.account', 'targets.placements', 'media']))]);
    }

    public function store(Request $request, DraftService $drafts): JsonResponse
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            ...$this->postBodyRules(),
            // The API also accepts a base_text-only create for external
            // consumers, so unlike the web FormRequest 'segments' isn't
            // required to be present.
            'segments' => ['sometimes', 'array'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $segments = isset($validated['segments']) && $validated['segments'] !== []
            ? array_values($validated['segments'])
            : [(string) ($validated['base_text'] ?? '')];

        $post = $drafts->createDraft(
            (string) Context::get('workspace_id'),
            $user,
            $validated['destination'],
            $segments,
            $validated['mentions'] ?? [],
            $validated['auto_repost'] ?? null,
            DraftData::fromArray($validated),
        );

        return response()->json(['post' => PostView::make($post->fresh(['targets.account', 'targets.placements', 'media']))], 201);
    }

    public function update(Request $request, string $id, DraftService $drafts): JsonResponse
    {
        $model = $this->findPostOrFail($id);
        $this->authorize('update', $model);

        $validated = $request->validate([
            ...$this->postBodyRules(),
            'segments' => ['sometimes', 'array'],
            ...$this->postEditRules(),
        ]);

        try {
            $updated = $drafts->updateDraft($model, DraftData::fromArray($validated));
        } catch (PostStaleWriteException) {
            return response()->json([
                'post' => PostView::make($model->fresh(['targets.account', 'targets.placements', 'media'])),
                'message' => 'stale_write',
            ], 409);
        }

        return response()->json(['post' => PostView::make($updated->fresh(['targets.account', 'targets.placements', 'media']))]);
    }

    /**
     * Copy a terminal post into a new draft — the JSON equivalent of the web
     * duplicate action, which redirected to the new draft's page.
     */
    public function duplicate(string $id, PostDuplicator $duplicator): JsonResponse
    {
        $model = $this->findPostOrFail($id);

        $this->authorize('create', Post::class);

        // Only terminal posts are eligible — mirror the client capability model
        // so the endpoint contract can't be bypassed for a draft/scheduled post.
        if (! in_array($model->status, [
            PostStatus::Published, PostStatus::Partial, PostStatus::Failed, PostStatus::Missed,
        ], true)) {
            abort(422, 'This post cannot be copied to a draft.');
        }

        $draft = $duplicator->duplicate($model);

        return response()->json([
            'post' => PostView::make($draft->fresh(['targets.account', 'targets.placements', 'media'])),
        ], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $model = $this->findPostOrFail($id);
        $this->authorize('delete', $model);

        $hadBeenPublished = in_array($model->status, [PostStatus::Published, PostStatus::Partial, PostStatus::Failed], true);

        $model->loadMissing('targets');

        if (! $hadBeenPublished) {
            $model->delete();

            return response()->json(['deleted' => true, 'remote' => false]);
        }

        $model->targets
            ->filter(fn (PostTarget $t): bool => $t->remote_id !== null)
            ->each(fn (PostTarget $t) => DeletePostTarget::dispatch($t));

        $model->forceFill(['status' => PostStatus::Deleted->value, 'deleted_at' => now()])->save();

        return response()->json(['deleted' => true, 'remote' => true, 'message' => 'Remote deletion queued for published targets.']);
    }
}
