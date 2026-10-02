<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspacePost;
use App\Http\Controllers\Controller;
use App\Jobs\PublishPostTarget;
use App\Models\PostTarget;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceSubscriptionGate;
use App\Services\Posts\NextSlotResolver;
use App\Services\Posts\PublishPrecheck;
use App\Services\Publishing\PostStatusRollup;
use App\Services\Publishing\PublishDispatcher;
use App\Support\PostView;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

class PostActionsController extends Controller
{
    use ResolvesWorkspacePost;

    public function schedule(Request $request, string $id): JsonResponse
    {
        $model = $this->findPostOrFail($id);
        $this->authorize('update', $model);

        $validated = $request->validate([
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ], [
            'scheduled_at.after' => 'Choose a time in the future — a post cannot be scheduled in the past.',
        ]);

        if (($validated['scheduled_at'] ?? null) !== null
            && ! app(WorkspaceSubscriptionGate::class)->canPublish($model->workspace()->firstOrFail())) {
            return $this->paymentRequired();
        }

        if (($validated['scheduled_at'] ?? null) !== null) {
            $model->scheduled_at = $validated['scheduled_at'];
            $model->status = PostStatus::Scheduled;
        } else {
            $model->scheduled_at = null;
            $model->status = PostStatus::Draft;
        }
        $model->save();

        return response()->json(['post' => PostView::make($model->fresh(['targets.account', 'media']))]);
    }

    public function queue(Request $request, string $id, NextSlotResolver $resolver): JsonResponse
    {
        $model = $this->findPostOrFail($id);
        $this->authorize('update', $model);
        $workspace = Workspace::query()->whereKey(Context::get('workspace_id'))->firstOrFail();

        if (! app(WorkspaceSubscriptionGate::class)->canPublish($workspace)) {
            return $this->paymentRequired();
        }

        $validated = $request->validate([
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        $availableSlots = $resolver->availableSlots($workspace);
        $slot = $this->resolveRequestedSlot($availableSlots, $validated['scheduled_at'] ?? null);

        if ($slot === null) {
            return response()->json([
                'message' => $request->filled('scheduled_at')
                    ? 'Choose an open slot from your posting queue.'
                    : 'No open posting slot available. Add posting-schedule slots in settings.',
            ], 422);
        }

        $model->scheduled_at = $slot;
        $model->status = PostStatus::Scheduled;
        $model->save();

        return response()->json(['post' => PostView::make($model->fresh(['targets.account', 'media']))]);
    }

    public function publish(string $id, PublishDispatcher $dispatcher, PublishPrecheck $precheck): JsonResponse
    {
        $model = $this->findPostOrFail($id);
        $this->authorize('update', $model);

        if ($model->loadMissing('targets')->targets->isEmpty()) {
            return response()->json([
                'message' => 'Select at least one account to publish.',
            ], 422);
        }

        if (! app(WorkspaceSubscriptionGate::class)->canPublish($model->workspace()->firstOrFail())) {
            return $this->paymentRequired();
        }

        $blocked = $precheck->blockingTargets($model->loadMissing(['targets.account', 'media']));
        if ($blocked !== []) {
            return response()->json([
                'message' => "Some accounts can't be published yet.",
                'blocked' => $blocked,
            ], 422);
        }

        $model->forceFill(['status' => PostStatus::Publishing->value])->save();
        $dispatcher->dispatchForPost($model);

        return response()->json([
            'status' => 'queued',
            'message' => 'Publishing started. Poll GET /posts/{id} for per-target status.',
            'post' => PostView::make($model->fresh(['targets.account', 'media'])),
        ], 202);
    }

    public function retry(string $id, string $targetId, PostStatusRollup $rollup): JsonResponse
    {
        $model = $this->findPostOrFail($id);
        $this->authorize('update', $model);

        $postTarget = PostTarget::query()->whereKey($targetId)->where('post_id', $model->id)->first();

        if ($postTarget === null) {
            abort(404, 'No such target on that post.');
        }

        if (! $postTarget->status->isRetryable()) {
            abort(422, 'Only failed or skipped targets can be retried.');
        }

        $postTarget->forceFill([
            'status' => PostTargetStatus::Pending->value,
            'error_kind' => null,
            'error_message' => null,
            'next_attempt_at' => null,
        ])->save();

        PublishPostTarget::dispatch($postTarget);
        $rollup->recompute($model);

        return response()->json([
            'status' => 'queued',
            'post' => PostView::make($model->fresh(['targets.account', 'media'])),
        ], 202);
    }

    private function paymentRequired(): JsonResponse
    {
        return response()->json([
            'message' => 'Subscribe to publish this post.',
            'billing_url' => route('billing.index'),
        ], 402);
    }

    /**
     * @param  list<CarbonImmutable>  $availableSlots
     */
    private function resolveRequestedSlot(array $availableSlots, ?string $requestedSlot): ?CarbonImmutable
    {
        if ($requestedSlot === null) {
            return $availableSlots[0] ?? null;
        }

        $requested = CarbonImmutable::parse($requestedSlot)
            ->setTimezone('UTC')
            ->toIso8601String();

        foreach ($availableSlots as $slot) {
            if ($slot->setTimezone('UTC')->toIso8601String() === $requested) {
                return $slot;
            }
        }

        return null;
    }
}
