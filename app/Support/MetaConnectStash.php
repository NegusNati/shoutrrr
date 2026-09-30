<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Platform;
use Illuminate\Http\Request;

/**
 * Session stash shared between the Meta OAuth callback (web route) and the
 * picker UI (SPA page backed by an /api/v1 session route). The callback stashes
 * the enumerated Pages/IG assets — including page access tokens — and the SPA
 * picker reads a token-stripped projection back over the API; the full stash is
 * only ever consumed server-side when the selection is stored.
 */
final class MetaConnectStash
{
    public const string KEY = 'accounts.meta.connect';

    /**
     * @param  array<string, array{pageId: string, pageName: string, pageAccessToken: string, igUserId: ?string, igUsername: ?string, igAvatarUrl: ?string}>  $assets
     */
    public static function put(Request $request, array $assets, ?string $userTokenExpiresAt): void
    {
        $request->session()->put(self::KEY, [
            'assets' => $assets,
            'userTokenExpiresAt' => $userTokenExpiresAt,
        ]);
    }

    /**
     * Reads the stash back as its declared shape. The session persists whatever
     * array was put in, but a foreign/corrupt payload is treated as absent
     * rather than trusted — each asset's fields are re-normalized.
     *
     * @return array{assets: array<string, array{pageId: string, pageName: string, pageAccessToken: string, igUserId: ?string, igUsername: ?string, igAvatarUrl: ?string}>, userTokenExpiresAt: ?string}|null
     */
    public static function get(Request $request): ?array
    {
        $stash = $request->session()->get(self::KEY);

        if (! is_array($stash) || ! is_array($stash['assets'] ?? null)) {
            return null;
        }

        $assets = [];

        foreach ($stash['assets'] as $key => $asset) {
            if (! is_string($key) || ! is_array($asset)) {
                return null;
            }

            foreach (['pageId', 'pageName', 'pageAccessToken'] as $field) {
                if (! is_string($asset[$field] ?? null)) {
                    return null;
                }
            }

            $assets[$key] = [
                'pageId' => $asset['pageId'],
                'pageName' => $asset['pageName'],
                'pageAccessToken' => $asset['pageAccessToken'],
                'igUserId' => is_string($asset['igUserId'] ?? null) ? $asset['igUserId'] : null,
                'igUsername' => is_string($asset['igUsername'] ?? null) ? $asset['igUsername'] : null,
                'igAvatarUrl' => is_string($asset['igAvatarUrl'] ?? null) ? $asset['igAvatarUrl'] : null,
            ];
        }

        return [
            'assets' => $assets,
            'userTokenExpiresAt' => is_string($stash['userTokenExpiresAt'] ?? null)
                ? $stash['userTokenExpiresAt']
                : null,
        ];
    }

    /**
     * Reads live session state that a concurrent OAuth callback may stash while
     * a lock is held, so its result can change between calls within one request.
     *
     * @phpstan-impure
     */
    public static function hasAssets(Request $request): bool
    {
        $stash = $request->session()->get(self::KEY);

        return is_array($stash) && is_array($stash['assets'] ?? null) && $stash['assets'] !== [];
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }

    /**
     * Strips secrets (page access tokens) for client consumption.
     *
     * @param  array<string, array{pageId: string, pageName: string, pageAccessToken: string, igUserId: ?string, igUsername: ?string, igAvatarUrl: ?string}>  $stashedAssets
     * @return list<array{key: string, pageId: string, pageName: string, igUserId: ?string, igUsername: ?string, igAvatarUrl: ?string, platforms: list<string>}>
     */
    public static function projectAssets(array $stashedAssets): array
    {
        $projected = [];

        foreach ($stashedAssets as $key => $asset) {
            $projected[] = [
                'key' => $key,
                'pageId' => $asset['pageId'],
                'pageName' => $asset['pageName'],
                'igUserId' => $asset['igUserId'],
                'igUsername' => $asset['igUsername'],
                'igAvatarUrl' => $asset['igAvatarUrl'],
                'platforms' => self::availablePlatformsFor($asset),
            ];
        }

        return $projected;
    }

    /**
     * @param  array{igUserId: ?string}  $asset
     * @return list<string>
     */
    public static function availablePlatformsFor(array $asset): array
    {
        $available = Platform::availableMetaGraphPlatforms();
        $platforms = [];

        if (in_array(Platform::Facebook, $available, true)) {
            $platforms[] = Platform::Facebook->value;
        }

        if (in_array(Platform::Instagram, $available, true) && $asset['igUserId'] !== null) {
            $platforms[] = Platform::Instagram->value;
        }

        return $platforms;
    }
}
