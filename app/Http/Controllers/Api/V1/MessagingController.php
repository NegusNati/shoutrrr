<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceConversation;
use App\Http\Controllers\Messaging\MessagingController as InboxMessagingController;
use App\Http\Requests\Messaging\RespondToMessageRequest;
use App\Models\Conversation;
use App\Services\Messaging\MessageConnectorRegistry;
use App\Services\Publishing\TokenManager;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API surface for the direct-message inbox. Every action delegates to the
 * shared web controller; the only differences are the JSON index payload and
 * the string route params (the api group has no implicit model binding).
 */
class MessagingController extends InboxMessagingController
{
    use ResolvesWorkspaceConversation;

    /**
     * Paginated conversation list (30 per page).
     */
    #[QueryParameter('archived', 'List archived conversations instead.', type: 'boolean')]
    #[QueryParameter('page', 'Page number.', type: 'integer')]
    public function inbox(Request $request): JsonResponse
    {
        return response()->json([
            'conversations' => $this->conversationPaginator($request),
            'filters' => ['archived' => $request->boolean('archived')],
        ]);
    }

    /** The conversation's messages, oldest first. */
    public function thread(Conversation|string $conversationId): JsonResponse
    {
        return parent::thread($this->findConversationOrFail($conversationId));
    }

    /** Mark the conversation read. */
    public function markRead(Conversation|string $conversationId): Response
    {
        return parent::markRead($this->findConversationOrFail($conversationId));
    }

    /** Archive the conversation. */
    public function archive(Conversation|string $conversationId): Response
    {
        return parent::archive($this->findConversationOrFail($conversationId));
    }

    /** Send a direct message. Body: { text?, media?[] } — one is required. */
    public function respond(
        RespondToMessageRequest $request,
        Conversation|string $conversationId,
        MessageConnectorRegistry $registry,
        TokenManager $tokens,
    ): JsonResponse {
        return parent::respond($request, $this->findConversationOrFail($conversationId), $registry, $tokens);
    }
}
