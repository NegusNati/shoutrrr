<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PostTargetStatus;
use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspacePost;
use App\Http\Controllers\Posts\PostMetricsRefreshController;
use App\Support\MetricsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostMetricsController extends PostMetricsRefreshController
{
    use ResolvesWorkspacePost;

    /**
     * Resolved equivalent of the old deferred `stats` payload: metrics for a
     * post once it has at least one published target, else null.
     */
    public function show(Request $request, string $postId): JsonResponse
    {
        $post = $this->findPostOrFail($postId);

        return response()->json([
            'stats' => $post->targets()->where('status', PostTargetStatus::Published->value)->exists()
                ? MetricsPresenter::forPost($post)
                : null,
        ]);
    }

    public function refresh(Request $request, string $postId): JsonResponse
    {
        return $this->store($request, $this->findPostOrFail($postId));
    }
}
