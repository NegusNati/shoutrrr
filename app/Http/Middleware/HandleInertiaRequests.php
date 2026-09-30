<?php

namespace App\Http\Middleware;

use App\Enums\Platform;
use App\Enums\SocialProvider;
use App\Models\User;
use App\Support\AppShellData;
use App\Support\FeedbackConfig;
use App\Support\InstanceSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Override;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    #[Override]
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    #[Override]
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function share(Request $request): array
    {
        // The shell payload is produced by AppShellData — the same source the
        // /api/v1 app endpoints use — so the Inertia pages and the SPA shell
        // can never drift. Closures stay lazy: Inertia filters props against
        // the partial request *before* resolving them, so a reload that asks
        // only for `shell.unreadReplies` never runs the workspace or account
        // queries.
        $shell = app(AppShellData::class);

        // Resolve the update-check once per request and share it across the
        // three deferred sidebar props. A request-local closure (not an instance
        // property) keeps this safe under Octane where the middleware instance
        // may be reused across requests.
        $update = null;
        $resolveUpdate = function () use (&$update, $shell): array {
            return $update ??= $shell->update();
        };

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'workspaces' => fn (): array => $shell->workspaces($request->user()),
            'shell' => $this->shellData($request->user(), $shell),
            'socialite' => [
                'providers' => SocialProvider::enabledProviders(),
            ],
            'flash' => [
                'success' => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => $request->hasSession() ? $request->session()->get('error') : null,
                'plainTextApiKey' => $request->hasSession() ? $request->session()->get('flash.plainTextApiKey') : null,
            ],
            'notifications' => fn (): array => $shell->notifications($request->user()),
            'features' => [
                'analytics' => app(InstanceSettings::class)->metricsEnabled(),
                'billing' => (bool) config('subscriptions.enabled'),
                'engagement' => app(InstanceSettings::class)->engagementEnabled(),
                'feedback' => FeedbackConfig::enabled(),
                'messages' => app(InstanceSettings::class)->messagesEnabled(),
            ],
            'instance' => [
                'isOwner' => $request->user()?->isInstanceOwner() ?? false,
            ],
            'billing' => Inertia::defer(fn () => $shell->billing($request->user()), 'sidebar'),
            'community' => Inertia::defer(fn () => $shell->community(), 'sidebar')->once(),
            'updateAvailable' => Inertia::defer(fn () => $resolveUpdate()['updateAvailable'], 'sidebar')->once(),
            'latestVersion' => Inertia::defer(fn () => $resolveUpdate()['latestVersion'], 'sidebar')->once(),
            'latestReleaseUrl' => Inertia::defer(fn () => $resolveUpdate()['latestReleaseUrl'], 'sidebar')->once(),
        ];
    }

    /**
     * Shell data needed by the sidebar, composer, and command palette on nearly
     * every page. Every member is a closure so partial reloads pay only for what
     * they ask for: the unread-badge poll requests `shell.unreadReplies` and
     * `shell.unreadMessages` and never touches the account or set queries.
     *
     * @return array{
     *     accounts: \Closure(): array<int, array<string, mixed>>,
     *     sets: \Closure(): array<int, array<string, mixed>>,
     *     limits: \Closure(): list<array<string, mixed>>,
     *     unreadReplies: \Closure(): int,
     *     unreadMessages: \Closure(): int,
     *     gifs_enabled: \Closure(): bool,
     * }
     */
    private function shellData(?User $user, AppShellData $shell): array
    {
        return [
            'accounts' => fn (): array => $shell->accounts($user),
            'sets' => fn (): array => $shell->sets($user?->current_workspace_id),
            'limits' => fn (): array => Platform::allLimits(),
            'unreadReplies' => fn (): int => $shell->unreadReplies($user),
            'unreadMessages' => fn (): int => $shell->unreadMessages($user),
            'gifs_enabled' => fn (): bool => $shell->gifsEnabled(),
        ];
    }
}
