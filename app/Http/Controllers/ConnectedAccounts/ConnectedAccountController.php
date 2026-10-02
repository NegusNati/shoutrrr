<?php

declare(strict_types=1);

namespace App\Http\Controllers\ConnectedAccounts;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;

/**
 * Shared card-view builder for connected accounts — the API index calls
 * view() so every surface renders identical data. The account mutations it
 * used to expose now live only on /api/v1/connected-accounts.
 */
class ConnectedAccountController extends Controller
{
    /**
     * Card payload for a connected account.
     *
     * @return array<string, mixed>
     */
    public static function view(ConnectedAccount $account, ?string $defaultAccountId): array
    {
        return [
            'id' => $account->id,
            'platform' => $account->platform->value,
            'platform_label' => $account->platform->label(),
            'handle' => $account->handle,
            'display_name' => $account->display_name,
            'avatar_url' => $account->avatar_url,
            'status' => $account->status->value,
            'status_label' => $account->status->label(),
            'auth_method' => $account->auth_method,
            'connected_by' => $account->connectedBy?->name,
            'token_expires_at' => $account->token_expires_at?->toIso8601String(),
            'max_text_length' => $account->maxTextLength(),
            'max_video_duration_seconds' => $account->maxVideoDurationSeconds(),
            'x_premium' => $account->hasXPremium(),
            'x_subscription_tier' => $account->xSubscriptionTier(),
            'x_subscription_label' => $account->xSubscriptionLabel(),
            'x_subscription_checked_at' => $account->xSubscriptionCheckedAt(),
            'is_linkedin_page' => $account->isLinkedInOrganization(),
            'is_default' => $account->id === $defaultAccountId,
            'disabled' => $account->isDisabled(),
            'pds_url' => self::customPdsUrl($account),
            'auto_repost_enabled' => $account->autoRepostEnabled(),
        ];
    }

    /**
     * The saved PDS for a Bluesky account, but only when it differs from the
     * default discovery target — so reconnect can re-run OAuth against a custom
     * service URL instead of silently falling back to bsky.social.
     */
    private static function customPdsUrl(ConnectedAccount $account): ?string
    {
        if ($account->platform !== Platform::Bluesky) {
            return null;
        }

        $pds = $account->secret?->session['pds'] ?? null;

        if (! is_string($pds) || $pds === '' || rtrim($pds, '/') === 'https://bsky.social') {
            return null;
        }

        return $pds;
    }
}
