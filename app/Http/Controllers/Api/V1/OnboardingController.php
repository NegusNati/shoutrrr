<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Onboarding mutations for the SPA. The legacy web endpoints answer with
 * redirects; here the responses stay JSON and carry the URL the client should
 * navigate to (workspace-setup pages still live outside the SPA).
 */
class OnboardingController extends Controller
{
    public function welcomed(Request $request): JsonResponse
    {
        $workspace = $this->currentWorkspace($request);
        $workspace->forceFill(['onboarding_welcomed_at' => now()])->save();

        return response()->json([
            'connect_url' => $request->boolean('connect')
                ? OnboardingStep::ConnectAccount->spaHref()
                : null,
        ]);
    }

    public function dismiss(Request $request): JsonResponse
    {
        $workspace = $this->currentWorkspace($request);

        abort_unless($workspace->connectedAccounts()->exists(), 409);

        $workspace->forceFill(['onboarding_dismissed_at' => now()])->save();

        return response()->json(null, 204);
    }

    /**
     * Mark a click-to-complete checklist step done. Only steps without a data
     * signal (timezone) use this path; the rest derive their done-state.
     */
    public function completeStep(Request $request): JsonResponse
    {
        $workspace = $this->currentWorkspace($request);

        $step = OnboardingStep::tryFrom((string) $request->input('key'));
        abort_if($step === null || ! $step->isClickToComplete(), 404);

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasAllPermissions([$step->permission()], $workspace->id), 403);

        /** @var list<string> $progress */
        $progress = $workspace->onboarding_progress ?? [];

        if (! in_array($step->value, $progress, true)) {
            $progress[] = $step->value;
            $workspace->forceFill(['onboarding_progress' => $progress])->save();
        }

        return response()->json([
            'redirect_url' => $step->spaHref(),
        ]);
    }

    private function currentWorkspace(Request $request): Workspace
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless(
            $workspace !== null && $user->isMemberOfWorkspace($workspace->id),
            403,
        );

        return $workspace;
    }
}
