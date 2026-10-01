<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Support\AppShellData;
use App\Support\FeedbackConfig;
use App\Support\InstanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Session bootstrap for the SPA: the JSON equivalent of the props
 * the old web-shell middleware shared with every page. One round
 * trip gives the client everything the app shell needs — the authenticated
 * user, workspaces, sidebar shell data, notifications, feature flags and
 * instance metadata.
 */
class MeController extends Controller
{
    public function show(Request $request, AppShellData $shell): JsonResponse
    {
        $user = $request->user();
        $settings = app(InstanceSettings::class);
        $update = $shell->update();

        return response()->json([
            'name' => config('app.name'),
            'auth' => ['user' => $user],
            'workspaces' => $shell->workspaces($user),
            'shell' => $shell->shell($user),
            'notifications' => $shell->notifications($user),
            'socialite' => [
                'providers' => SocialProvider::enabledProviders(),
            ],
            'features' => [
                'analytics' => $settings->metricsEnabled(),
                'billing' => (bool) config('subscriptions.enabled'),
                'engagement' => $settings->engagementEnabled(),
                'feedback' => FeedbackConfig::enabled(),
                'messages' => $settings->messagesEnabled(),
            ],
            'instance' => [
                'isOwner' => $user?->isInstanceOwner() ?? false,
            ],
            'billing' => $shell->billing($user),
            'community' => $shell->community(),
            'updateAvailable' => $update['updateAvailable'],
            'latestVersion' => $update['latestVersion'],
            'latestReleaseUrl' => $update['latestReleaseUrl'],
        ]);
    }
}
