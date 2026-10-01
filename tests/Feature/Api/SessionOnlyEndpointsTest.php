<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Session-only endpoints — Passport API keys get 403 before validation
|--------------------------------------------------------------------------
|
| User settings, workspace settings, connected accounts, sync pipelines and
| instance settings act on credentials and the signed-in user's current
| workspace, so RequireSessionAuth rejects API-key traffic on all of them.
| Dual-auth endpoints (posts, engagement, …) stay reachable for automation.
|
*/

test('api keys cannot reach session-only endpoints', function (string $method, string $uri) {
    [, , $token] = issuedKey();

    $this->withToken($token)->json($method, $uri)->assertForbidden();
})->with([
    'profile read' => ['GET', '/api/v1/settings/profile'],
    'profile update' => ['PUT', '/api/v1/settings/profile'],
    'profile delete' => ['DELETE', '/api/v1/settings/profile'],
    'security' => ['GET', '/api/v1/settings/security'],
    'password' => ['PUT', '/api/v1/settings/password'],
    'connections' => ['GET', '/api/v1/settings/connections'],
    'connections delete' => ['DELETE', '/api/v1/settings/connections/soc_1'],
    'notifications prefs' => ['GET', '/api/v1/settings/notifications'],
    'notifications prefs update' => ['PUT', '/api/v1/settings/notifications'],
    'workspace overview' => ['GET', '/api/v1/settings/workspace'],
    'workspace update' => ['PATCH', '/api/v1/settings/workspace'],
    'workspace timezone' => ['PUT', '/api/v1/settings/workspace/timezone'],
    'workspace members' => ['GET', '/api/v1/settings/workspace/members'],
    'workspace invite' => ['POST', '/api/v1/settings/workspace/invite'],
    'workspace member role' => ['PATCH', '/api/v1/settings/workspace/members/1'],
    'workspace member remove' => ['DELETE', '/api/v1/settings/workspace/members/1'],
    'workspace invitation cancel' => ['DELETE', '/api/v1/settings/workspace/invitations/1'],
    'api keys list' => ['GET', '/api/v1/settings/workspace/api-keys'],
    'api keys create' => ['POST', '/api/v1/settings/workspace/api-keys'],
    'api keys revoke' => ['DELETE', '/api/v1/settings/workspace/api-keys/key_1'],
    'subscription' => ['GET', '/api/v1/settings/workspace/subscription'],
    'billing checkout' => ['POST', '/api/v1/billing/checkout'],
    'billing portal' => ['POST', '/api/v1/billing/portal'],
    'connected accounts' => ['GET', '/api/v1/connected-accounts'],
    'connect meta pending' => ['GET', '/api/v1/connected-accounts/connect/meta'],
    'connect linkedin pending' => ['GET', '/api/v1/connected-accounts/connect/linkedin'],
    'connect bluesky' => ['POST', '/api/v1/connected-accounts/connect/bluesky'],
    'connect discord' => ['POST', '/api/v1/connected-accounts/connect/discord'],
    'connect meta submit' => ['POST', '/api/v1/connected-accounts/connect/meta'],
    'connect linkedin submit' => ['POST', '/api/v1/connected-accounts/connect/linkedin'],
    'account toggle' => ['PATCH', '/api/v1/connected-accounts/acc_1/toggle'],
    'account default' => ['POST', '/api/v1/connected-accounts/acc_1/default'],
    'account auto repost' => ['PATCH', '/api/v1/connected-accounts/acc_1/auto-repost'],
    'account refresh tier' => ['POST', '/api/v1/connected-accounts/acc_1/refresh-x-tier'],
    'account reconnect' => ['POST', '/api/v1/connected-accounts/acc_1/reconnect'],
    'account destroy' => ['DELETE', '/api/v1/connected-accounts/acc_1'],
    'sync list' => ['GET', '/api/v1/sync-pipelines'],
    'sync create' => ['POST', '/api/v1/sync-pipelines'],
    'sync update' => ['PATCH', '/api/v1/sync-pipelines/pipe_1'],
    'sync delete' => ['DELETE', '/api/v1/sync-pipelines/pipe_1'],
    'sync track native' => ['POST', '/api/v1/sync-pipelines/native-tracking/acc_1'],
    'sync untrack native' => ['DELETE', '/api/v1/sync-pipelines/native-tracking/acc_1'],
    'instance settings' => ['GET', '/api/v1/instance-settings'],
    'instance update' => ['PUT', '/api/v1/instance-settings'],
    'instance polling' => ['GET', '/api/v1/instance-settings/polling'],
    'instance admins' => ['GET', '/api/v1/instance-settings/admins'],
]);

test('session users still reach session-only endpoints', function (string $method, string $uri) {
    ownerActingIn();

    $this->json($method, $uri)->assertSuccessful();
})->with([
    'profile read' => ['GET', '/api/v1/settings/profile'],
    'workspace overview' => ['GET', '/api/v1/settings/workspace'],
    'connected accounts' => ['GET', '/api/v1/connected-accounts'],
    'sync list' => ['GET', '/api/v1/sync-pipelines'],
]);
