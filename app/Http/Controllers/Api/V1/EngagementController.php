<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceReply;
use App\Http\Controllers\Engagement\EngagementController as InboxEngagementController;
use App\Http\Requests\Engagement\RespondToReplyRequest;
use App\Models\PostTargetReply;
use App\Services\Engagement\EngagementConnectorRegistry;
use App\Services\Publishing\TokenManager;
use App\Support\InstanceSettings;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API surface for the engagement inbox. Every action delegates to the shared
 * web controller; the only differences are the JSON index payload and the
 * string route params (the api group has no implicit model binding).
 */
class EngagementController extends InboxEngagementController
{
    use ResolvesWorkspaceReply;

    /**
     * Paginated conversation groups with the inbox's filters, facets, feature
     * flags, and saved mentions.
     */
    #[QueryParameter('account', 'Connected account id.', type: 'string')]
    #[QueryParameter('platform', 'Platform filter (x, bluesky, linkedin, ...).', type: 'string')]
    #[QueryParameter('target', 'Post target id.', type: 'string')]
    #[QueryParameter('post', 'Post id.', type: 'string')]
    #[QueryParameter('unread', 'Only unread conversations.', type: 'boolean')]
    #[QueryParameter('archived', 'List archived conversations instead.', type: 'boolean')]
    #[QueryParameter('page', 'Page number (25 conversations per page).', type: 'integer')]
    public function inbox(Request $request, InstanceSettings $settings): JsonResponse
    {
        return response()->json([
            'replies' => $this->conversationPaginator($this->engagementReplyFilter($request), $request),
            ...$this->engagementIndexProps($request, $settings),
        ]);
    }

    /** The full conversation thread rooted at the given reply's base reply. */
    public function thread(PostTargetReply|string $replyId): JsonResponse
    {
        return parent::thread($this->findReplyOrFail($replyId));
    }

    /** Mark every inbound reply in the conversation read. */
    public function markRead(PostTargetReply|string $replyId): Response
    {
        return parent::markRead($this->findReplyOrFail($replyId));
    }

    /** Archive every inbound reply in the conversation. */
    public function archive(PostTargetReply|string $replyId): Response
    {
        return parent::archive($this->findReplyOrFail($replyId));
    }

    /** Reply to the conversation. Body: { text?, media?[] } — one is required. */
    public function respond(
        RespondToReplyRequest $request,
        PostTargetReply|string $replyId,
        EngagementConnectorRegistry $registry,
        TokenManager $tokens,
    ): JsonResponse {
        return parent::respond($request, $this->findReplyOrFail($replyId), $registry, $tokens);
    }

    /** Like the reply on the platform. */
    public function like(
        PostTargetReply|string $replyId,
        EngagementConnectorRegistry $registry,
        TokenManager $tokens,
    ): JsonResponse {
        return parent::like($this->findReplyOrFail($replyId), $registry, $tokens);
    }

    /** Remove the platform like from the reply. */
    public function unlike(
        PostTargetReply|string $replyId,
        EngagementConnectorRegistry $registry,
        TokenManager $tokens,
    ): JsonResponse {
        return parent::unlike($this->findReplyOrFail($replyId), $registry, $tokens);
    }

    /** Delete an outbound reply (only replies the workspace posted). */
    public function destroyReply(
        PostTargetReply|string $replyId,
        EngagementConnectorRegistry $registry,
        TokenManager $tokens,
    ): Response|JsonResponse {
        return parent::destroyReply($this->findReplyOrFail($replyId), $registry, $tokens);
    }
}
