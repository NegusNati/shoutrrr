<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\WorkspaceSubscriptionGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Subscription/billing overview for the current workspace. Only reachable
 * when subscriptions are enabled on the instance (404 otherwise, matching
 * the web route). Checkout/portal stay web routes — they 302 into Stripe.
 */
class WorkspaceSubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless(config('subscriptions.enabled'), 404);

        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        abort_unless($user->hasAllPermissions(['workspace.billing.manage'], $workspace->id), 403);

        $subscribed = $workspace->subscribed('default');
        $subscriptionGate = app(WorkspaceSubscriptionGate::class);
        $remainingBudget = $subscriptionGate->remainingXBudgetMicrousd($workspace);

        return response()->json([
            'subscribed' => $subscribed,
            'monthlyPrice' => (int) config('subscriptions.monthly_price_cents'),
            'monthlyXBudgetMicrousd' => $subscriptionGate->monthlyXBudgetMicrousd($workspace),
            'monthlyXBudgetUsedMicrousd' => $subscriptionGate->currentXCostMicrousd($workspace),
            'monthlyXBudgetRemainingMicrousd' => $remainingBudget === PHP_INT_MAX ? null : $remainingBudget,
            'canManageSubscription' => $subscribed,
            'canAccessPortal' => $workspace->hasStripeId(),
        ]);
    }
}
