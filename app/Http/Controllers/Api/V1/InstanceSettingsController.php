<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InstanceRole;
use App\Http\Controllers\Settings\InstanceSettingsController as WebInstanceSettingsController;
use App\Http\Requests\Settings\StoreInstanceOwnerRequest;
use App\Http\Requests\Settings\UpdateInstancePlatformsRequest;
use App\Http\Requests\Settings\UpdateInstancePollingSettingsRequest;
use App\Http\Requests\Settings\UpdateInstanceSettingsRequest;
use App\Http\Requests\Settings\UpdateWorkspaceXBudgetRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceSubscriptionGate;
use App\Support\InstanceSettings;
use App\Support\UsagePricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Instance-owner settings for the SPA and API keys: the same payloads the
 * Inertia settings pages render, exposed as JSON. Method names avoid the web
 * controller's (its signatures return Inertia/Redirect responses).
 */
class InstanceSettingsController extends WebInstanceSettingsController
{
    public function show(Request $request, InstanceSettings $settings): JsonResponse
    {
        $this->requireInstanceOwner($request);

        return response()->json($this->instancePayload($settings));
    }

    public function updateSettings(UpdateInstanceSettingsRequest $request, InstanceSettings $settings): JsonResponse
    {
        $settings->update($request->instanceSettings());

        return response()->json($this->instancePayload($settings));
    }

    public function showPolling(Request $request, InstanceSettings $settings): JsonResponse
    {
        $this->requireInstanceOwner($request);

        return response()->json($this->pollingPayload($settings));
    }

    public function updatePollingSettings(UpdateInstancePollingSettingsRequest $request, InstanceSettings $settings): JsonResponse
    {
        $settings->update($request->instancePollingSettings());

        return response()->json($this->pollingPayload($settings));
    }

    public function showPlatforms(Request $request, InstanceSettings $settings): JsonResponse
    {
        $this->requireInstanceOwner($request);

        return response()->json($this->platformsPayload($settings));
    }

    public function updatePlatformSettings(UpdateInstancePlatformsRequest $request, InstanceSettings $settings): JsonResponse
    {
        $settings->update([
            'platforms_enabled' => $request->platformsEnabled(),
            'linkedin_community_management_enabled' => $request->linkedinCommunityManagementEnabled(),
        ]);

        return response()->json($this->platformsPayload($settings));
    }

    public function showUsage(
        Request $request,
        UsagePricing $pricing,
        InstanceSettings $settings,
        WorkspaceSubscriptionGate $gate,
    ): JsonResponse {
        $this->requireInstanceOwner($request);

        $payload = $this->usagePayload($request, $pricing, $settings, $gate);

        $workspaceId = $request->string('workspace')->trim()->toString() ?: null;

        if ($workspaceId !== null) {
            $defaultDollars = (int) config('subscriptions.monthly_x_budget_cents') / 100;
            $payload['drilldown'] = $this->workspaceDrilldown($workspaceId, $pricing, $gate, $settings, $defaultDollars);
        }

        return response()->json($payload);
    }

    public function updateBudget(
        UpdateWorkspaceXBudgetRequest $request,
        Workspace $workspace,
        InstanceSettings $settings,
    ): JsonResponse {
        $settings->setXWorkspaceBudget($workspace->id, $request->budgetValue());

        return response()->json(['message' => 'Workspace X budget updated.']);
    }

    public function listAdmins(Request $request): JsonResponse
    {
        $this->requireInstanceOwner($request);

        $search = $request->string('search')->trim()->toString();

        return response()->json([
            'owners' => $this->instanceOwners(),
            'users' => $this->searchableUsers($search),
            'search' => $search,
        ]);
    }

    public function addAdmin(StoreInstanceOwnerRequest $request): JsonResponse
    {
        $owner = User::query()->where('email', $request->email())->firstOrFail();
        $owner->update(['instance_role' => InstanceRole::Owner->value]);

        return response()->json([
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'avatar' => $owner->avatar,
                'created_at' => $owner->created_at,
            ],
        ], 201);
    }

    public function removeAdmin(Request $request, User $owner): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->requireInstanceOwner($request);
        abort_unless($owner->isInstanceOwner(), 404);

        if (($error = $this->ownerRemovalError($user, $owner)) !== null) {
            return response()->json(['message' => $error, 'errors' => ['owner' => [$error]]], 422);
        }

        $owner->update(['instance_role' => null]);

        return response()->json(['message' => 'Instance owner removed.']);
    }

    private function requireInstanceOwner(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isInstanceOwner(), 403);
    }
}
