<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PostTargetStatus;
use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspacePost;
use App\Http\Controllers\Controller;
use App\Jobs\CapturePostTargetMetrics;
use App\Models\PostTarget;
use App\Support\MetricsPresenter;
use Illuminate\Http\JsonResponse;

class PostMetricsRefreshController extends Controller
{
    use ResolvesWorkspacePost;

    /** Read-only rollup for the published view's initial render. */
    public function show(string $id): JsonResponse
    {
        $post = $this->findPostOrFail($id);
        $this->authorize('view', $post);

        return response()->json(MetricsPresenter::forPost($post));
    }

    public function store(string $id): JsonResponse
    {
        $post = $this->findPostOrFail($id);
        $this->authorize('view', $post);

        $post->targets()
            ->where('status', PostTargetStatus::Published->value)
            ->whereNotNull('remote_id')
            ->get()
            ->each(fn (PostTarget $target) => CapturePostTargetMetrics::dispatchSync($target));

        return response()->json(MetricsPresenter::forPost($post->fresh() ?? $post));
    }
}
