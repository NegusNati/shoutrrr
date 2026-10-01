<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\JsonResponse;

/**
 * Public invitation lookup by bearer token — feeds the SPA's
 * /app/invitation/{token} page. Invalid/expired tokens 404, same as the legacy
 * redirect-with-error.
 */
class PublicInvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invitation = WorkspaceInvitation::findByToken($token);
        abort_unless($invitation !== null && $invitation->isValid(), 404);

        return response()->json([
            'invitation' => [
                'token' => $token,
                'id' => $invitation->id,
                'workspace_name' => $invitation->workspace->name,
                'role' => $invitation->role,
                'inviter_name' => $invitation->inviter()->value('name') ?? 'Someone',
                'expires_at' => $invitation->expires_at,
            ],
            'userExists' => User::where('email', $invitation->email)->exists(),
            'loginUrl' => '/app/login?'.http_build_query(['invitation' => $token]),
            'registerUrl' => '/app/register?'.http_build_query(['invitation' => $token]),
        ]);
    }
}
