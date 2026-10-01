<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Support\AnalyticsData;
use App\Support\InstanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request, InstanceSettings $settings, AnalyticsData $analytics): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->can('viewAny', Post::class), 403);

        $days = max(7, min(365, (int) $request->integer('days', 90)));

        return response()->json([
            ...$analytics->build($days),
            'rangeDays' => $days,
            'polling' => [
                'post_metrics_enabled' => collect(Platform::cases())
                    ->mapWithKeys(fn (Platform $platform): array => [
                        $platform->value => $settings->postMetricsPollingEnabled($platform),
                    ])
                    ->all(),
                'account_metrics_enabled' => collect(Platform::cases())
                    ->mapWithKeys(fn (Platform $platform): array => [
                        $platform->value => $settings->accountMetricsPollingEnabled($platform),
                    ])
                    ->all(),
            ],
        ]);
    }
}
