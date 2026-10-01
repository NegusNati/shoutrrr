<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BillingController as WebBillingController;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Checkout;

class BillingController extends WebBillingController
{
    public function show(Request $request): JsonResponse
    {
        abort_unless(config('subscriptions.enabled'), 404);

        $workspace = $this->apiWorkspace();
        $this->authorizeManageBilling($request, $workspace);

        return response()->json($this->billingPayload($workspace));
    }

    /**
     * Returns the Stripe-hosted URL the client must navigate to — JSON callers
     * can't follow a web redirect into an external origin.
     */
    public function createCheckout(Request $request): JsonResponse
    {
        abort_unless(config('subscriptions.enabled'), 404);

        $workspace = $this->apiWorkspace();
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        $this->authorizeManageBilling($request, $workspace);

        $checkout = $this->startCheckout($workspace, $user);

        if (! $checkout instanceof Checkout) {
            throw ValidationException::withMessages(['billing' => $checkout]);
        }

        return response()->json(['url' => $checkout->redirect()->getTargetUrl()]);
    }

    public function createPortal(Request $request): JsonResponse
    {
        abort_unless(config('subscriptions.enabled'), 404);

        $workspace = $this->apiWorkspace();
        $this->authorizeManageBilling($request, $workspace);

        abort_unless($workspace->hasStripeId(), 404);

        return response()->json([
            'url' => $workspace->redirectToBillingPortal(route('billing.index'))->getTargetUrl(),
        ]);
    }

    private function apiWorkspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Workspace::query()->whereKey(Context::get('workspace_id'))->firstOrFail();

        return $workspace;
    }
}
