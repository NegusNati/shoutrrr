<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\AnalyticsController as WebAnalyticsController;
use App\Models\Post;
use App\Support\InstanceSettings;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API surface for the analytics page — the same account/post/summary/comparison
 * payload the SPA page renders, as JSON.
 */
class AnalyticsController extends WebAnalyticsController
{
    /**
     * Follower series, post markers, headline summary, and engagement
     * comparison for the workspace.
     */
    #[QueryParameter('days', 'Lookback window in days, clamped to 7–365.', type: 'integer', example: 90)]
    public function report(Request $request, InstanceSettings $settings): JsonResponse
    {
        abort_unless($request->user()->can('viewAny', Post::class), 403);

        return response()->json($this->analyticsIndexData($request, $settings));
    }
}
