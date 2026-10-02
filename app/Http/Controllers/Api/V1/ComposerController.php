<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspacePost;
use App\Http\Controllers\Posts\ComposerController as WebComposerController;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComposerController extends WebComposerController
{
    use ResolvesWorkspacePost;

    /**
     * Full composer payload for the SPA — same shape the old page received,
     * with `metricsEnabled` instead of the deferred `stats` prop (the SPA lazy
     * loads stats via the metrics-refresh endpoint when needed).
     */
    public function showCompose(Request $request, string $postId): JsonResponse
    {
        $request->user()->can('viewAny', Post::class) ?: abort(403);

        return response()->json($this->composePayload($request, $this->findPostOrFail($postId)));
    }
}
